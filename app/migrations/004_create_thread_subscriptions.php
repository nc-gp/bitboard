<?php

return [
    'version' => 4,
    'name' => 'create thread subscriptions',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_thread_subscriptions (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_thread_subscription (user_id, thread_id),
            KEY thread_subscriptions_thread (thread_id)
        )');
    },
];
