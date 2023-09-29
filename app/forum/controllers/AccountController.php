<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class AccountController
{
	static public function GetAccountByName(Database $db, string $userName): array
	{
		return $db->Query('SELECT * FROM bit_accounts WHERE username = ? LIMIT 1', $userName)->FetchArray();
	}

    static public function UpdateLastActive(Database $db, int $userId): void
    {
        $db->Query('UPDATE bit_accounts SET last_active = ? WHERE id = ?', date("Y-m-d H:i:s"), $userId);
    }

    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_accounts (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			username VARCHAR(32) NOT NULL,
			pass VARCHAR(1024) NOT NULL,
			email VARCHAR(64) NOT NULL,
			avatar VARCHAR(255) NULL DEFAULT NULL,
			reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			reputation INT(6) NOT NULL DEFAULT 0,
			last_ip VARCHAR(64),
			last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			rank_id INT(6) NOT NULL
		)');
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