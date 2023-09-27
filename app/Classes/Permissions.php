<?php

namespace App\Classes;

class Permissions
{
    // Normal permissions
    const VIEWING_FORUM = 1;
    const VIEWING_FORUM_LOCKED = 2;
    const CREATING_POSTS = 4;
    const EDITING_POSTS = 8;
    const DELETING_POSTS = 16;
    const CREATING_THREADS = 32;
    const EDITING_THREADS = 64;
    const CREATING_THREADS_LOCKED = 128;
    const CREATING_POSTS_LOCKED = 256;

    // Some 'admin' permissions
    const DELETING_THREADS = 512;
    const PINNING_THREADS = 1024;
    const LOCKING_THREADS = 2048;
    const ADMIN_PANEL_ACCESS = 4096; // panel
    const MANAGING_USERS = 8192;
    const MANAGING_ROLES = 16384;
    const VIEWING_REPORTS = 32768;
    const MANAGING_SETTINGS = 65536;

    // All permisions in one
    public static int $All = 
    self::VIEWING_FORUM |
    self::VIEWING_FORUM_LOCKED |
    self::CREATING_POSTS |
    self::EDITING_POSTS |
    self::DELETING_POSTS |
    self::CREATING_THREADS |
    self::EDITING_THREADS |
    self::DELETING_THREADS |
    self::PINNING_THREADS |
    self::LOCKING_THREADS |
    self::CREATING_THREADS_LOCKED |
    self::CREATING_POSTS_LOCKED |
    self::ADMIN_PANEL_ACCESS |
    self::MANAGING_USERS |
    self::MANAGING_ROLES |
    self::VIEWING_REPORTS |
    self::MANAGING_SETTINGS;

    /**
     * Check if a user has a specific permission.
     *
     * @param int $userPermissions The user's permission bitmask.
     * @param int $permission      The permission constant to check.
     * @return bool
     */
    public static function hasPermission($userPermissions, $permission)
    {
        return ($userPermissions & $permission) !== 0;
    }

    /**
     * Grant a permission to a user's permission bitmask.
     *
     * @param int $userPermissions The user's permission bitmask.
     * @param int $permission      The permission constant to grant.
     * @return int
     */
    public static function grantPermission($userPermissions, $permission)
    {
        return $userPermissions | $permission;
    }

    /**
     * Revoke a permission from a user's permission bitmask.
     *
     * @param int $userPermissions The user's permission bitmask.
     * @param int $permission      The permission constant to revoke.
     * @return int
     */
    public static function revokePermission($userPermissions, $permission)
    {
        return $userPermissions & ~$permission;
    }
}

?>