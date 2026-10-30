<?php

return [
    'version' => 11,
    'name' => 'add rank positions',
    'up' => static function (\App\Classes\Database $db): void {
        if (\App\Classes\Migrator::ColumnExists($db, 'bit_ranks', 'rank_position'))
            return;

        $db->Query('ALTER TABLE bit_ranks ADD COLUMN rank_position INT(6) NOT NULL DEFAULT 0');
        $db->Query('UPDATE bit_ranks SET rank_position = 100 WHERE id = 1');
        $db->Query('UPDATE bit_ranks SET rank_position = 50 WHERE id = 2');
        $db->Query('UPDATE bit_ranks SET rank_position = 0 WHERE id = 3');
        $db->Query('UPDATE bit_ranks SET rank_position = 10 WHERE id > 3 AND rank_position = 0');
    },
];
