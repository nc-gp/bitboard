<?php

return [
    'version' => 17,
    'name' => 'key the settings row',
    'up' => static function (\App\Classes\Database $db): void {
        if (!\App\Classes\Migrator::ColumnExists($db, 'bit_settings', 'settings_key'))
            $db->Query("ALTER TABLE bit_settings ADD COLUMN settings_key VARCHAR(32) NOT NULL DEFAULT 'core' AFTER id");

        $db->Query("UPDATE bit_settings SET settings_key = 'core'
            WHERE settings_key = '' OR settings_key IS NULL");

        $primary = $db->Query("SELECT column_name FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'bit_settings' AND index_name = 'PRIMARY'
            ORDER BY seq_in_index LIMIT 1")->FetchArray();
        $primaryColumn = (string) ($primary['column_name'] ?? '');

        if ($primaryColumn === 'id')
        {
            $db->Query('ALTER TABLE bit_settings DROP PRIMARY KEY, DROP COLUMN id, ADD PRIMARY KEY (settings_key)');
        }
        elseif (\App\Classes\Migrator::ColumnExists($db, 'bit_settings', 'id'))
        {
            $db->Query('ALTER TABLE bit_settings DROP COLUMN id');
        }

        $primary = $db->Query("SELECT column_name FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'bit_settings' AND index_name = 'PRIMARY'
            ORDER BY seq_in_index LIMIT 1")->FetchArray();
        $primaryColumn = (string) ($primary['column_name'] ?? '');

        if ($primaryColumn !== 'settings_key')
        {
            $db->Query('ALTER TABLE bit_settings MODIFY settings_key VARCHAR(32) NOT NULL');
            $db->Query('ALTER TABLE bit_settings ADD PRIMARY KEY (settings_key)');
        }

        // The key is always "core"; the primary key prevents duplicate rows.
        // CHECK is enforced by MySQL 8.0.16+ and MariaDB, and ignored by older
        // MySQL versions where the application only ever writes the core key.
        try
        {
            $db->Query("ALTER TABLE bit_settings ADD CONSTRAINT chk_settings_single_row CHECK (settings_key = 'core')");
        }
        catch (\Throwable $e)
        {
            error_log('BitBoard migration 17: single-row check skipped: ' . $e->getMessage());
        }
    },
];
