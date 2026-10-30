<?php

return [
    'version' => 2,
    'name' => 'create content edit tables',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_thread_edits (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            thread_id INT(6) NOT NULL,
            user_id INT(6) NOT NULL,
            thread_title VARCHAR(128) NOT NULL,
            thread_content VARCHAR(4096) NOT NULL,
            edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY thread_edits_thread (thread_id)
        )');

        $db->Query('CREATE TABLE IF NOT EXISTS bit_post_edits (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id INT(6) NOT NULL,
            user_id INT(6) NOT NULL,
            post_content VARCHAR(4096) NOT NULL,
            edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY post_edits_post (post_id)
        )');
    },
];
