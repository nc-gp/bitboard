<?php

require_once './app/helpers/url_manager.php';

/**
 * The Avatar class provides methods for generating avatar image paths.
 */
class Avatar
{
    /**
     * Get the path to an avatar image based on the provided theme and avatar name.
     *
     * @param string $theme      The theme to use for the avatar.
     * @param string $avatarName The name of the avatar image.
     *
     * @return string The full path to the avatar image.
     */
    static public function GetPath(string $theme, string $avatarName): string
    {
        if($avatarName == 'default.webp')
            return UrlManager::GetPath() . '//themes//' . $theme . '/images/default.webp';
        else
            return UrlManager::GetPath() . '/assets/images/avatars/' . $avatarName;
    }
}

?>