<?php

return [
    'version' => 19,
    'name' => 'use an accessible admin rank format',
    'up' => static function (\App\Classes\Database $db): void {
        // The original installer used a fixed firebrick inline color that fails
        // WCAG contrast on dark backgrounds. The class is styled by the theme
        // and inherits readable colors when a custom theme does not define it.
        $db->Query(
            "UPDATE bit_ranks SET rank_format = '<span class=\"rank-admin\">{username}</span>'
                WHERE rank_name = 'Admin'
                    AND rank_format = '<span style=\"color: #B22222\">{username}</span>'"
        );
    },
];
