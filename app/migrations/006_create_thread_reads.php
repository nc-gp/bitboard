<?php

return [
    'version' => 6,
    'name' => 'create thread read positions',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_thread_reads (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            last_read_post_id INT(6) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_thread_read (user_id, thread_id),
            KEY thread_reads_thread (thread_id)
        )');
    },
];
