<?php

return [
    'version' => 18,
    'name' => 'flag the system account',
    'up' => static function (\App\Classes\Database $db): void {
        if (!\App\Classes\Migrator::ColumnExists($db, 'bit_accounts', 'is_system'))
            $db->Query('ALTER TABLE bit_accounts ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER id');

        $system = $db->Query("SELECT id FROM bit_accounts WHERE username = 'BitBot' ORDER BY id ASC LIMIT 1")->FetchArray();

        if (empty($system))
        {
            // Very old or manually created installs may have renamed the bot.
            $system = $db->Query('SELECT id FROM bit_accounts ORDER BY id ASC LIMIT 1')->FetchArray();
        }

        if (!empty($system))
        {
            $db->Query('UPDATE bit_accounts SET is_system = 0 WHERE is_system = 1 AND id <> ?', (int) $system['id']);
            $db->Query('UPDATE bit_accounts SET is_system = 1 WHERE id = ?', (int) $system['id']);
        }

        if (!\App\Classes\Migrator::IndexExists($db, 'bit_accounts', 'accounts_system'))
            $db->Query('ALTER TABLE bit_accounts ADD KEY accounts_system (is_system)');
    },
];
