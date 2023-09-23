<?php

namespace App\Forum\Controllers;

use App\Classes\Console;
use App\Forum\Controllers\RankController;
use App\Classes\UsernameUtils;

class AccountController
{
    public array $Data;
    public bool $IsGood = true;
    private int $ID;

    public function __construct($db, int $accountId)
    {
        $this->ID = $accountId;

        $this->Data = $db->Query('SELECT * FROM bit_accounts WHERE id = ? LIMIT 1', $this->ID)->FetchArray();

        if(sizeof($this->Data) <= 0)
        {
            $this->IsGood = false;
            Console::Error('Couldn\'t fetch account with ID ' . $this->ID);
        }
        else
        {
            $this->Data['rank'] = new RankController($db, $this->Data['rank_id']);
            $this->Data['formatted_username'] = UsernameUtils::Format($this->Data['rank']->Data['rank_format'], $this->Data['username']);
        }
    }

    public function Delete($db)
    {
        $db->Query('DELETE FROM bit_accounts WHERE id = ?', $this->ID);
    }

    public function UpdateUsername($db, string $newUsername)
    {
        $db->Query('UPDATE bit_accounts SET username = ? WHERE id = ?', [$newUsername, $this->ID]);
    }

    public function UpdatePassword($db, $newPassword) // give hashed one!
    {
        $db->Query('UPDATE bit_accounts SET pass = ? WHERE id = ?', [$newPassword, $this->ID]);
    }

    public function UpdateAvatar($db, string $avatarName)
    {
        $db->Query('UPDATE bit_accounts SET avatar = ? WHERE id = ?', [$avatarName, $this->ID]);
    }

    public function HasPermission(int $permissionToCheck): bool
    {
        if ($this->Data['rank']['rank_flags'] & $permissionToCheck)
            return true;

        return false;
    }
    
    static public function Create($db, string $username, string $hashedPassword, string $email, int $rankId)
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