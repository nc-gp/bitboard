<?php

return [
    'version' => 5,
    'name' => 'create forum subscriptions',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_forum_subscriptions (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            forum_id INT(6) NOT NULL,
            subforum_id INT(6) NOT NULL DEFAULT -1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_forum_subscription (user_id, forum_id, subforum_id),
            KEY forum_subscriptions_target (forum_id, subforum_id)
        )');
    },
];
