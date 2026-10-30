<?php

return [
    'version' => 10,
    'name' => 'add configurable admin path',
    'up' => static function (\App\Classes\Database $db): void {
        if (!\App\Classes\Migrator::ColumnExists($db, 'bit_settings', 'admin_path'))
            $db->Query("ALTER TABLE bit_settings ADD COLUMN admin_path VARCHAR(64) NOT NULL DEFAULT 'admin'");
    },
];
