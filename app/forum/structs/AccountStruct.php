<?php

namespace App\Forum\Structs;

use App\Classes\Permissions;
use App\Classes\Database;
use App\Classes\SessionManager;

class AccountStruct
{
    /**
     * @var int The unique identifier of the user account.
     */
    public $id;

    /**
     * @var string The name associated with the user account.
     */
    public $username;

    /**
     * @var string The hashed password of the user account.
     */
    public $pass;

    /**
     * @var string The email address of the user account.
     */
    public $email;

    /**
     * @var string The path to the avatar associated with the user account.
     */
    public $avatar;

    /**
     * @var string The registration date of the user account.
     */
    public $reg_date;

    /**
     * @var int The reputation score of the user account.
     */
    public $reputation;

    /**
     * @var string The last known IP address of the user account.
     */
    public $last_ip;

    /**
     * @var string The timestamp of the last activity of the user account.
     */
    public $last_active;

    /**
     * @var string The timestamp of the last login of the user account.
     */
    public $last_login;

    /**
     * @var int The identifier of the user's rank.
     */
    public $rank_id;

    /**
     * @var int The bitmask of permissions.
     * Note: Can be 0.
     */
    public $permissions;

    /**
     * AccountStruct constructor.
     *
     * @param array $accountData An associative array containing category data.
     */
    public function __construct(array $accountData)
    {
        foreach($accountData as $key => $value)
            $this->{$key} = $value;
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param int $permission The permission to check.
     * @return bool Returns true if the user has the specified permission, otherwise false.
     */
    public function HasPermission(int $permission)
    {
        return Permissions::hasPermission($this->permissions, $permission);
    }

    /**
     * Update the user account's data, including permissions, from the database.
     *
     * @param Database $db The database connection.
     */
    public function Update(Database $db)
    {
        $account = $db->Query('SELECT a.*, r.rank_flags AS permissions FROM bit_accounts AS a LEFT JOIN bit_ranks AS r ON r.id = a.rank_id WHERE a.id = ?', $this->id)->FetchArray();
        SessionManager::Set($account);
    }
}

?>