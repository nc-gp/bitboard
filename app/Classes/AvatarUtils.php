<?php

namespace App\Classes;

use App\Classes\UrlManager;

/**
 * The Avatar class provides methods for generating avatar image paths.
 */
class AvatarUtils
{
    /**
     * Get the path to an avatar image based on the provided theme and avatar name.
     *
     * @param string $avatarName The name of the avatar image.
     *
     * @return string The full path to the avatar image.
     */
    static public function GetPath(string $avatarName): string
    {
        $defaultPath = UrlManager::GetPath() . 'themes/' . BB_THEME . '/images/default.webp';

        if($avatarName === '' || $avatarName === 'default.webp' || $avatarName !== basename($avatarName))
            return $defaultPath;

        $extension = strtolower(pathinfo($avatarName, PATHINFO_EXTENSION));
        if(!in_array($extension, array('webp', 'jpg', 'jpeg', 'png', 'gif'), true))
            return $defaultPath;

        $avatarFile = __DIR__ . '/../../assets/images/avatars/' . $avatarName;
        if(!is_file($avatarFile))
            return $defaultPath;

        return UrlManager::GetPath() . 'assets/images/avatars/' . rawurlencode($avatarName);
    }
}

?>