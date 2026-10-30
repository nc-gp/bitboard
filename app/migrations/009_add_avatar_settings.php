<?php

return [
    'version' => 9,
    'name' => 'add avatar settings',
    'up' => static function (\App\Classes\Database $db): void {
        $columns = [
            'avatar_uploads' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'avatar_max_size' => 'INT(6) NOT NULL DEFAULT 2048',
            'avatar_formats' => "VARCHAR(64) NOT NULL DEFAULT 'jpg,png,webp,gif'",
            'avatar_max_width' => 'INT(6) NOT NULL DEFAULT 0',
            'avatar_max_height' => 'INT(6) NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $column => $definition)
        {
            if (!\App\Classes\Migrator::ColumnExists($db, 'bit_settings', $column))
                $db->Query('ALTER TABLE bit_settings ADD COLUMN `' . $column . '` ' . $definition);
        }
    },
];
