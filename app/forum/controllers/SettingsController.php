<?php

namespace App\Forum\Controllers;

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
