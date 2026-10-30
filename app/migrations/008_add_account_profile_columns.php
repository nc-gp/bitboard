<?php

return [
    'version' => 8,
    'name' => 'add account profile and viewing columns',
    'up' => static function (\App\Classes\Database $db): void {
        $columns = [
            'bio' => "VARCHAR(1000) NOT NULL DEFAULT ''",
            'signature' => "VARCHAR(500) NOT NULL DEFAULT ''",
            'viewing_type' => "VARCHAR(16) NOT NULL DEFAULT ''",
            'viewing_id' => 'INT(6) UNSIGNED NOT NULL DEFAULT 0',
        ];

        foreach ($columns as $column => $definition)
        {
            if (!\App\Classes\Migrator::ColumnExists($db, 'bit_accounts', $column))
                $db->Query('ALTER TABLE bit_accounts ADD COLUMN `' . $column . '` ' . $definition);
        }
    },
];
