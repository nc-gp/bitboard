<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Forum settings helpers, including avatar upload limits.
 */
class SettingsController
{
    public const AVATAR_FORMAT_MIME = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    private static bool $schemaChecked = false;

    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $columns = $db->Query("SELECT column_name FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'bit_settings'")->FetchAll();
            $names = array_column($columns, 'column_name');

            if (!in_array('avatar_uploads', $names, true))
                $db->Query('ALTER TABLE bit_settings ADD COLUMN avatar_uploads TINYINT(1) NOT NULL DEFAULT 1');

            if (!in_array('avatar_max_size', $names, true))
                $db->Query('ALTER TABLE bit_settings ADD COLUMN avatar_max_size INT(6) NOT NULL DEFAULT 2048');

            if (!in_array('avatar_formats', $names, true))
                $db->Query("ALTER TABLE bit_settings ADD COLUMN avatar_formats VARCHAR(64) NOT NULL DEFAULT 'jpg,png,webp,gif'");

            if (!in_array('avatar_max_width', $names, true))
                $db->Query('ALTER TABLE bit_settings ADD COLUMN avatar_max_width INT(6) NOT NULL DEFAULT 0');

            if (!in_array('avatar_max_height', $names, true))
                $db->Query('ALTER TABLE bit_settings ADD COLUMN avatar_max_height INT(6) NOT NULL DEFAULT 0');

            if (!in_array('admin_path', $names, true))
                $db->Query("ALTER TABLE bit_settings ADD COLUMN admin_path VARCHAR(64) NOT NULL DEFAULT 'admin'");
        }
        catch (Throwable $e)
        {
            error_log('BitBoard settings schema check failed: ' . $e->getMessage());
        }
    }

    /**
     * @return string[] Normalized avatar extensions.
     */
    public static function AllowedAvatarFormats(string $formats): array
    {
        $parts = array_filter(array_map('trim', explode(',', strtolower($formats))));
        $allowed = [];

        foreach ($parts as $part)
        {
            if ($part === 'jpeg')
                $part = 'jpg';

            if (isset(self::AVATAR_FORMAT_MIME[$part]))
                $allowed[] = $part;
        }

        $allowed = array_values(array_unique($allowed));

        return count($allowed) > 0 ? $allowed : array_keys(self::AVATAR_FORMAT_MIME);
    }

    /**
     * @return array<string, string> MIME type => extension.
     */
    public static function AvatarMimeMap(string $formats): array
    {
        $map = [];

        foreach (self::AllowedAvatarFormats($formats) as $extension)
            $map[self::AVATAR_FORMAT_MIME[$extension]] = $extension;

        return $map;
    }

    public static function AvatarFormatLabel(string $formats): string
    {
        $labels = [
            'jpg' => 'JPEG',
            'png' => 'PNG',
            'webp' => 'WebP',
            'gif' => 'GIF',
        ];

        $parts = array_map(
            static fn(string $extension): string => $labels[$extension] ?? strtoupper($extension),
            self::AllowedAvatarFormats($formats)
        );

        return implode(', ', $parts);
    }
}
