<?php

return [
    'version' => 7,
    'name' => 'create reports',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_reports (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            post_id INT(6) NOT NULL DEFAULT 0,
            reason VARCHAR(32) NOT NULL,
            details VARCHAR(1000) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'open\',
            note VARCHAR(1000) NOT NULL DEFAULT \'\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_by INT(6) NULL DEFAULT NULL,
            resolved_at TIMESTAMP NULL DEFAULT NULL,
            KEY reports_status (status, created_at),
            KEY reports_thread (thread_id),
            KEY reports_post (post_id)
        )');
    },
];
