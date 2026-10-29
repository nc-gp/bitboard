<?php

namespace App\Classes;

use App\Classes\UrlManager;
use App\Classes\Template;

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
        $theme = Template::GetTheme();
        $themeImage = __DIR__ . '/../../themes/' . $theme . '/images/default.webp';

        if (!is_file($themeImage))
            $theme = 'default';

        $defaultPath = UrlManager::GetPath() . 'themes/' . $theme . '/images/default.webp';

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