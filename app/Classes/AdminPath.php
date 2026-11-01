<?php

namespace App\Classes;

use Throwable;

/**
 * Resolves and validates the configurable admin panel route segment.
 */
class AdminPath
{
    public const DEFAULT_PATH = 'admin';

    /**
     * Existing front-controller routes and directories that must never be used
     * as the admin path.
     */
    private const RESERVED = [
        '404', 'api', 'app', 'assets', 'attachment', 'banned', 'dumps', 'edit', 'feed',
        'feed.xml', 'forum', 'index', 'like', 'login', 'logout', 'members',
        'moderate', 'move', 'notifications', 'offline', 'online', 'post',
        'preview', 'profile', 'register', 'report', 'reports', 'search',
        'settings', 'sitemap', 'sitemap.xml', 'subforum', 'subscribe',
        'themes', 'thread', 'watch',
    ];

    /** @var array<int, string> */
    private static array $databaseCache = [];

    /**
     * Trim slashes and lowercase a candidate path.
     */
    public static function Normalize(string $path): string
    {
        return strtolower(trim(trim($path), '/'));
    }

    /**
     * A valid path is 3-64 characters, lowercase alphanumeric plus `_`/`-`,
     * and does not collide with an existing route.
     */
    public static function IsValid(string $path): bool
    {
        $path = self::Normalize($path);

        if ($path === '' || in_array($path, self::RESERVED, true))
            return false;

        return preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/', $path) === 1;
    }

    /**
     * Resolve the path from already-loaded forum settings.
     */
    public static function FromSettings(object $data): string
    {
        $path = isset($data->admin_path) && is_string($data->admin_path)
            ? self::Normalize($data->admin_path)
            : '';

        return self::IsValid($path) ? $path : self::DEFAULT_PATH;
    }

    /**
     * Resolve the path directly from the database (used by shared widgets).
     */
    public static function FromDatabase(Database $db): string
    {
        $key = spl_object_id($db);

        if (isset(self::$databaseCache[$key]))
            return self::$databaseCache[$key];

        $path = '';

        try
        {
            $row = $db->Query("SELECT admin_path FROM bit_settings WHERE settings_key = 'core'")->FetchArray();
            $path = self::Normalize((string) ($row['admin_path'] ?? ''));
        }
        catch (Throwable $e)
        {
            error_log('BitBoard admin path lookup failed: ' . $e->getMessage());
        }

        $path = self::IsValid($path) ? $path : self::DEFAULT_PATH;
        self::$databaseCache[$key] = $path;

        return $path;
    }
}
