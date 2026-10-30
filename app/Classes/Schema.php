<?php

namespace App\Classes;

use Throwable;

/**
 * Idempotent schema upgrades for existing installations.
 *
 * The existing state is read in two information_schema queries; only missing
 * indexes and defaults cause an ALTER TABLE.
 */
class Schema
{
    /**
     * Add the indexes and column defaults that fresh installs receive directly
     * from the CREATE TABLE statements.
     */
    public static function EnsureCore(Database $db): void
    {
        try
        {
            $rows = $db->Query("SELECT table_name, index_name FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                    AND table_name IN ('bit_accounts', 'bit_threads', 'bit_posts')")->FetchAll();
        }
        catch (Throwable $e)
        {
            error_log('BitBoard schema index lookup failed: ' . $e->getMessage());
            $rows = [];
        }

        $indexes = [];

        foreach ($rows as $row)
            $indexes[$row['table_name'] . '.' . $row['index_name']] = true;

        self::AddIndex($db, $indexes, 'bit_accounts', 'uniq_accounts_username', ['username'], true);
        self::AddIndex($db, $indexes, 'bit_accounts', 'uniq_accounts_email', ['email'], true);
        self::AddIndex($db, $indexes, 'bit_accounts', 'accounts_last_active', ['last_active']);
        self::AddIndex($db, $indexes, 'bit_threads', 'threads_forum', ['forum_id', 'subforum_id']);
        self::AddIndex($db, $indexes, 'bit_threads', 'threads_user', ['user_id']);
        self::AddIndex($db, $indexes, 'bit_posts', 'posts_thread', ['thread_id']);
        self::AddIndex($db, $indexes, 'bit_posts', 'posts_user', ['user_id']);

        try
        {
            $rows = $db->Query("SELECT table_name, column_name, column_default FROM information_schema.columns
                WHERE table_schema = DATABASE()
                    AND ((table_name = 'bit_threads' AND column_name IN ('is_closed', 'is_pinned', 'prefix_id', 'thread_likes'))
                        OR (table_name = 'bit_posts' AND column_name = 'post_likes'))")->FetchAll();
        }
        catch (Throwable $e)
        {
            error_log('BitBoard schema default lookup failed: ' . $e->getMessage());
            $rows = [];
        }

        $defaults = [];

        foreach ($rows as $row)
            $defaults[$row['table_name'] . '.' . $row['column_name']] = $row['column_default'];

        self::AddDefault($db, $defaults, 'bit_threads', 'is_closed', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::AddDefault($db, $defaults, 'bit_threads', 'is_pinned', 'TINYINT(1) NOT NULL DEFAULT 0');
        self::AddDefault($db, $defaults, 'bit_threads', 'prefix_id', 'INT(6) NOT NULL DEFAULT 0');
        self::AddDefault($db, $defaults, 'bit_threads', 'thread_likes', 'INT(6) NOT NULL DEFAULT 0');
        self::AddDefault($db, $defaults, 'bit_posts', 'post_likes', 'INT(6) NOT NULL DEFAULT 0');
    }

    /**
     * @param array<string, bool> $existing
     * @param string[]            $columns
     */
    private static function AddIndex(Database $db, array $existing, string $table, string $index, array $columns, bool $unique = false): void
    {
        if (isset($existing[$table . '.' . $index]))
            return;

        try
        {
            if ($unique && count($columns) === 1 && self::HasDuplicates($db, $table, $columns[0]))
            {
                error_log('BitBoard schema: skipped unique index ' . $index . ' because ' . $table . '.' . $columns[0] . ' contains duplicates.');
                return;
            }

            $quoted = array_map(static fn (string $column): string => '`' . $column . '`', $columns);

            $db->Query(
                'ALTER TABLE `' . $table . '` ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $index . '` ('
                . implode(', ', $quoted) . ')'
            );
        }
        catch (Throwable $e)
        {
            error_log('BitBoard schema index ' . $index . ' failed: ' . $e->getMessage());
        }
    }

    private static function HasDuplicates(Database $db, string $table, string $column): bool
    {
        $row = $db->Query(
            'SELECT `' . $column . '` AS duplicate_value FROM `' . $table . '`
                GROUP BY `' . $column . '` HAVING COUNT(*) > 1 LIMIT 1'
        )->FetchArray();

        return !empty($row);
    }

    /**
     * @param array<string, string|null> $defaults
     */
    private static function AddDefault(Database $db, array $defaults, string $table, string $column, string $definition): void
    {
        if (!array_key_exists($table . '.' . $column, $defaults) || $defaults[$table . '.' . $column] !== null)
            return;

        try
        {
            $db->Query('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` ' . $definition);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard schema default for ' . $table . '.' . $column . ' failed: ' . $e->getMessage());
        }
    }
}
