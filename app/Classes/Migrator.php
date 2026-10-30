<?php

namespace App\Classes;

use RuntimeException;
use Throwable;

/**
 * Dependency-free numbered migration runner.
 *
 * Migration files live in app/migrations and return an array:
 *
 *     [
 *         'version' => 1,
 *         'name' => 'create audit log',
 *         'up' => static function (Database $db): void { ... },
 *     ]
 *
 * Applied versions are recorded in bit_migrations. A request only queries the
 * latest applied version after the runner has caught up, so normal requests do
 * not inspect information_schema or run DDL.
 */
class Migrator
{
    public const TABLE = 'bit_migrations';
    private const LOCK_NAME = 'bitboard_migrations';
    private const LOCK_TIMEOUT = 20;

    /** @var array<int, array{version: int, name: string, up: callable}>|null */
    private static ?array $migrations = null;

    /**
     * Apply every pending migration in order.
     *
     * @return array<int, array{version: int, name: string}>
     */
    public static function Run(Database $db): array
    {
        $latest = self::LatestVersion();

        if ($latest === 0)
            return [];

        $applied = self::Applied($db);

        if (isset($applied[$latest]) && count($applied) >= $latest)
            return [];

        if (!self::AcquireLock($db))
            throw new RuntimeException('Unable to acquire the database migration lock.');

        try
        {
            // Another request may have completed the upgrade while we waited.
            $applied = self::Applied($db);
            $ran = [];

            foreach (self::Migrations() as $version => $migration)
            {
                if (isset($applied[$version]))
                    continue;

                try
                {
                    ($migration['up'])($db);
                    self::Record($db, $version, $migration['name']);
                }
                catch (Throwable $e)
                {
                    error_log('BitBoard migration ' . $version . ' (' . $migration['name'] . ') failed: ' . $e->getMessage());

                    throw new RuntimeException(
                        'Database upgrade failed at migration ' . $version . ' (' . $migration['name'] . '). Check the PHP error log.',
                        0,
                        $e
                    );
                }

                $ran[] = [
                    'version' => $version,
                    'name' => $migration['name'],
                ];
            }

            return $ran;
        }
        finally
        {
            self::ReleaseLock($db);
        }
    }

    /**
     * @return array<int, array{version: int, name: string, applied_at: string|null}>
     */
    public static function Status(Database $db): array
    {
        $applied = self::Applied($db);
        $status = [];

        foreach (self::Migrations() as $version => $migration)
        {
            $status[] = [
                'version' => $version,
                'name' => $migration['name'],
                'applied_at' => isset($applied[$version]) ? (string) $applied[$version]['applied_at'] : null,
            ];
        }

        return $status;
    }

    /**
     * Highest migration number found on disk. Filenames are NNN_name.php.
     */
    public static function LatestVersion(): int
    {
        $latest = 0;

        foreach (self::Files() as $file)
            $latest = max($latest, self::VersionFromFile($file));

        return $latest;
    }

    public static function TableExists(Database $db, string $table): bool
    {
        $row = $db->Query(
            'SELECT COUNT(*) AS total FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = ?',
            $table
        )->FetchArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    public static function ColumnExists(Database $db, string $table, string $column): bool
    {
        $row = $db->Query(
            'SELECT COUNT(*) AS total FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            $table,
            $column
        )->FetchArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    public static function IndexExists(Database $db, string $table, string $index): bool
    {
        $row = $db->Query(
            'SELECT COUNT(*) AS total FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            $table,
            $index
        )->FetchArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    public static function ConstraintExists(Database $db, string $table, string $constraint): bool
    {
        $row = $db->Query(
            'SELECT COUNT(*) AS total FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ?
                    AND constraint_type = \'FOREIGN KEY\'',
            $table,
            $constraint
        )->FetchArray();

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * @return array<int, array{version: int, name: string, up: callable}>
     */
    private static function Migrations(): array
    {
        if (self::$migrations !== null)
            return self::$migrations;

        $migrations = [];
        $expected = 1;

        foreach (self::Files() as $file)
        {
            $version = self::VersionFromFile($file);
            $migration = require $file;

            if (!is_array($migration)
                || !isset($migration['version'], $migration['name'], $migration['up'])
                || !is_int($migration['version'])
                || !is_string($migration['name'])
                || !is_callable($migration['up']))
                throw new RuntimeException('Invalid migration file: ' . basename($file));

            if ($migration['version'] !== $version)
                throw new RuntimeException('Migration version does not match its filename: ' . basename($file));

            if ($version !== $expected)
                throw new RuntimeException('Migration versions must be sequential; expected ' . $expected . ', found ' . $version . '.');

            $migrations[$version] = [
                'version' => $version,
                'name' => $migration['name'],
                'up' => $migration['up'],
            ];

            $expected++;
        }

        return self::$migrations = $migrations;
    }

    /**
     * @return array<int, array{version: int, name: string, applied_at: string}>
     */
    private static function Applied(Database $db): array
    {
        try
        {
            $rows = $db->Query('SELECT version, name, applied_at FROM ' . self::TABLE . ' ORDER BY version')->FetchAll();
        }
        catch (Throwable $e)
        {
            self::CreateTable($db);
            return [];
        }

        $applied = [];

        foreach ($rows as $row)
        {
            $applied[(int) $row['version']] = [
                'version' => (int) $row['version'],
                'name' => (string) $row['name'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $applied;
    }

    private static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            version INT(6) UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private static function Record(Database $db, int $version, string $name): void
    {
        $db->Query(
            'INSERT INTO ' . self::TABLE . ' (version, name, applied_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), applied_at = VALUES(applied_at)',
            $version,
            $name,
            date('Y-m-d H:i:s')
        );
    }

    private static function AcquireLock(Database $db): bool
    {
        try
        {
            $row = $db->Query('SELECT GET_LOCK(?, ?) AS acquired', self::LOCK_NAME, self::LOCK_TIMEOUT)->FetchArray();

            return (int) ($row['acquired'] ?? 0) === 1;
        }
        catch (Throwable $e)
        {
            error_log('BitBoard migration lock failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function ReleaseLock(Database $db): void
    {
        try
        {
            $db->Query('SELECT RELEASE_LOCK(?)', self::LOCK_NAME)->FetchArray();
        }
        catch (Throwable $e)
        {
            error_log('BitBoard migration unlock failed: ' . $e->getMessage());
        }
    }

    /**
     * @return string[]
     */
    private static function Files(): array
    {
        $files = glob(__DIR__ . '/../migrations/[0-9][0-9][0-9]_*.php') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    private static function VersionFromFile(string $file): int
    {
        return (int) substr(basename($file), 0, 3);
    }
}
