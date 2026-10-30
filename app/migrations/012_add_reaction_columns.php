<?php

return [
    'version' => 12,
    'name' => 'add reaction columns',
    'up' => static function (\App\Classes\Database $db): void {
        if (\App\Classes\Migrator::TableExists($db, 'bit_posts_likes')
            && !\App\Classes\Migrator::ColumnExists($db, 'bit_posts_likes', 'reaction'))
            $db->Query("ALTER TABLE bit_posts_likes ADD COLUMN reaction VARCHAR(16) NOT NULL DEFAULT 'heart' AFTER post_id");

        if (\App\Classes\Migrator::TableExists($db, 'bit_threads_likes')
            && !\App\Classes\Migrator::ColumnExists($db, 'bit_threads_likes', 'reaction'))
            $db->Query("ALTER TABLE bit_threads_likes ADD COLUMN reaction VARCHAR(16) NOT NULL DEFAULT 'heart' AFTER thread_id");
    },
];
