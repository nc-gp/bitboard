<?php

return [
    'version' => 14,
    'name' => 'create login attempts',
    'up' => static function (\App\Classes\Database $db): void {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_login_attempts (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT(6) UNSIGNED NOT NULL DEFAULT 0,
            ip VARCHAR(64) NOT NULL,
            identifier VARCHAR(64) NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_ip (ip, attempted_at),
            INDEX idx_login_attempts_account (account_id, attempted_at),
            INDEX idx_login_attempts_identifier (identifier, attempted_at),
            INDEX idx_login_attempts_time (attempted_at)
        )');
    },
];
