<?php

namespace App\Classes;

class Permissions
{
    const VIEWING_FORUM = 1;
    const VIEWING_FORUM_LOCKED = 2;
    const CREATING_POSTS = 4;
    const EDITING_POSTS = 8;
    const DELETING_POSTS = 16;
    const CREATING_THREADS = 32;
    const EDITING_THREADS = 64;
    const DELETING_THREADS = 128;
    const PINNING_THREADS = 256;
    const LOCKING_THREADS = 512;
    const CREATING_THREADS_LOCKED = 1024;
    const CREATING_POSTS_LOCKED = 2048;

    // Additional Permissions
    const MANAGING_USERS = 4096;
    const MANAGING_ROLES = 8192;
    const VIEWING_REPORTS = 16384;
    const MANAGING_SETTINGS = 32768;

    const ALL_PERMISSIONS = 65535; // A bitmask with all permissions turned on.

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