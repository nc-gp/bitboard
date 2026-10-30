<?php

return [
    'version' => 3,
    'name' => 'create notifications',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_notifications (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            actor_id INT(6) NOT NULL,
            type VARCHAR(32) NOT NULL,
            thread_id INT(6) NOT NULL DEFAULT 0,
            post_id INT(6) NOT NULL DEFAULT 0,
            message VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY notifications_user (user_id, is_read, created_at),
            KEY notifications_post (post_id)
        )');
    },
];
