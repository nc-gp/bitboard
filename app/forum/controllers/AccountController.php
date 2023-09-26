<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class AccountController
{
    static public function UpdateLastActive(Database $db, int $userId): void
    {
        $db->Query('UPDATE bit_accounts SET last_active = ? WHERE id = ?', date("Y-m-d H:i:s"), $userId);
    }

    static public function Create(Database $db, string $username, string $hashedPassword, string $email, int $rankId)
    {
        $registerDate = date("Y-m-d H:i:s");

        $db->Query('INSERT INTO bit_accounts 
			(id,username,pass,email,avatar,reg_date,reputation,last_ip,last_active,last_login,rank_id) 
			VALUES (?,?,?,?,?,?,?,?,?,?,?)', 
			[0, $username, $hashedPassword, $email, 'default.webp', $registerDate, 0, $_SERVER['REMOTE_ADDR'], $registerDate, $registerDate, $rankId]
		);
    }
}

?>