<?php

namespace App\Forum\Structs;

use App\Classes\Permissions;
use App\Classes\Database;
use App\Classes\SessionManager;

class AccountStruct
{
    public $id;
    public $name;
    public $hashed_password;
    public $avatar;
    public $registration_date;
    public $reputation;
    public $last_ip;
    public $last_active;
    public $last_login;
    public $rank_id;
    public $permissions;

    public function __construct($id, $name, $hashed_password, $avatar, $registration_date, $reputation, $last_ip, $last_active, $last_login, $rank_id, $permissions)
    {
        $this->id = $id;
        $this->name = $name;
        $this->hashed_password = $hashed_password;
        $this->avatar = $avatar;
        $this->registration_date = $registration_date;
        $this->reputation = $reputation;
        $this->last_ip = $last_ip;
        $this->last_active = $last_active;
        $this->last_login = $last_login;
        $this->rank_id = $rank_id;
        $this->permissions = $permissions;
    }

    public function HasPermission(int $permission)
    {
        return Permissions::hasPermission($this->permissions, $permission);
    }

    public function Update(Database $db)
    {
        $account = $db->Query('SELECT * FROM bit_accounts WHERE id = ? LIMIT 1', $this->id)->FetchArray();
        $permissions = $db->Query('SELECT rank_flags FROM bit_ranks WHERE id = ?', $account['rank_id'])->FetchArray();
        $account['permissions'] = $permissions['rank_flags'];

        SessionManager::Set($account);
    }
}

?>