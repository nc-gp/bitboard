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
     * @param string $theme      The theme to use for the avatar.
     * @param string $avatarName The name of the avatar image.
     *
     * @return string The full path to the avatar image.
     */
    static public function GetPath(string $avatarName): string
    {
        if($avatarName == 'default.webp')
            return UrlManager::GetPath() . 'themes\\' . BB_THEME . '\images\default.webp';
        else
            return UrlManager::GetPath() . 'assets\images/avatars\\' . $avatarName;
    }
}

?>