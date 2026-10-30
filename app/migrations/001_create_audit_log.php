<?php

return [
    'version' => 1,
    'name' => 'create audit log',
    'up' => static function (\App\Classes\Database $db): void {
        if (!\App\Classes\Migrator::TableExists($db, 'bit_audit_log'))
        {
            $db->Query('CREATE TABLE IF NOT EXISTS bit_audit_log (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_id INT(6) NOT NULL DEFAULT 0,
                action VARCHAR(64) NOT NULL,
                target_type VARCHAR(32) NOT NULL DEFAULT \'\',
                target_id INT(6) NOT NULL DEFAULT 0,
                details VARCHAR(1000) NOT NULL DEFAULT \'\',
                ip_address VARCHAR(64) NOT NULL DEFAULT \'\',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY audit_created (created_at),
                KEY audit_actor (actor_id),
                KEY audit_action (action)
            )');
        }

        $admin = $db->Query('SELECT rank_flags FROM bit_ranks WHERE id = 1')->FetchArray();

        if (!empty($admin) && (((int) $admin['rank_flags'] & \App\Classes\Permissions::VIEWING_AUDIT_LOG) === 0))
        {
            $db->Query(
                'UPDATE bit_ranks SET rank_flags = rank_flags | ? WHERE id = 1',
                \App\Classes\Permissions::VIEWING_AUDIT_LOG
            );
        }
    },
];
