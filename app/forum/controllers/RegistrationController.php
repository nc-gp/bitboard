<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

/**
 * Registration throttling and email-domain policy helpers.
 */
class RegistrationController
{
    public const WINDOW = 3600; // 1 hour
    private const RETENTION = 604800; // 7 days

    public const EMAIL_MODES = [
        'off' => 'Disabled',
        'allow' => 'Allowlist only',
        'block' => 'Blocklist',
    ];

    public const DEFAULT_DOMAINS = [
        'gmail.com',
        'outlook.com',
        'hotmail.com',
        'yahoo.com',
        'icloud.com',
        'protonmail.com',
    ];

    private static ?bool $tableAvailable = null;

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_registration_attempts (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(64) NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_registration_ip (ip, attempted_at),
            INDEX idx_registration_time (attempted_at)
        )');
    }

    public static function IsIpBlocked(Database $db, string $ip, int $limit): bool
    {
        if (!self::EnsureTable($db))
            return false;

        $limit = max(1, $limit);

        try
        {
            $row = $db->Query('SELECT COUNT(*) AS total FROM bit_registration_attempts
                WHERE ip = ? AND attempted_at >= ?',
                self::NormalizeIp($ip),
                self::WindowStart()
            )->FetchArray();

            return (int) ($row['total'] ?? 0) >= $limit;
        }
        catch (\Throwable $e)
        {
            error_log('BitBoard registration throttle check failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function RecordAttempt(Database $db, string $ip): void
    {
        if (!self::EnsureTable($db))
            return;

        try
        {
            $db->Query('INSERT INTO bit_registration_attempts (ip, attempted_at) VALUES (?, ?)',
                self::NormalizeIp($ip),
                date('Y-m-d H:i:s')
            );

            $db->Query('DELETE FROM bit_registration_attempts WHERE attempted_at < ?',
                date('Y-m-d H:i:s', time() - self::RETENTION)
            );
        }
        catch (\Throwable $e)
        {
            error_log('BitBoard registration throttle write failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, string> Normalized domain names.
     */
    public static function ParseDomains(string $domains): array
    {
        $parts = preg_split('/[\s,]+/', strtolower($domains)) ?: [];
        $valid = [];

        foreach ($parts as $part)
        {
            $domain = ltrim(trim($part), '@');

            if ($domain === '' || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain))
                continue;

            $valid[$domain] = true;
        }

        return array_keys($valid);
    }

    public static function NormalizeDomains(string $domains): string
    {
        return implode("\n", self::ParseDomains($domains));
    }

    public static function EmailAllowed(string $email, string $mode, string $domains): bool
    {
        if ($mode === 'off')
            return true;

        $at = strrpos($email, '@');

        if ($at === false)
            return false;

        $domain = strtolower(substr($email, $at + 1));
        $listed = false;

        foreach (self::ParseDomains($domains) as $allowedDomain)
        {
            if ($domain === $allowedDomain || str_ends_with($domain, '.' . $allowedDomain))
            {
                $listed = true;
                break;
            }
        }

        return $mode === 'allow' ? $listed : !$listed;
    }

    public static function ModeLabel(string $mode): string
    {
        return self::EMAIL_MODES[$mode] ?? self::EMAIL_MODES['off'];
    }

    private static function EnsureTable(Database $db): bool
    {
        if (self::$tableAvailable !== null)
            return self::$tableAvailable;

        try
        {
            $table = $db->Query("SHOW TABLES LIKE 'bit\\_registration\\_attempts'")->FetchArray();

            if (empty($table))
                self::CreateTable($db);

            self::$tableAvailable = true;
        }
        catch (\Throwable $e)
        {
            self::$tableAvailable = false;
            error_log('BitBoard registration throttle setup failed: ' . $e->getMessage());
        }

        return self::$tableAvailable;
    }

    private static function WindowStart(): string
    {
        return date('Y-m-d H:i:s', time() - self::WINDOW);
    }

    private static function NormalizeIp(string $ip): string
    {
        $ip = trim($ip);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }
}
