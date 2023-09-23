<?php

namespace App\Classes;

/**
 * The UsernameUtils class provides static methods for working with user usernames.
 */
class UsernameUtils
{
    /**
     * Validate the username length.
     *
     * @param string $username The username string to validate.
     * @return bool True if the username meets the length requirement; false otherwise.
     */
    public static function Validate(string $username): bool
    {
        return strlen($username) >= 4;
    }

    /**
     * Format a username using a provided rank format.
     *
     * @param string $rankFormat The format string with "{username}" as a placeholder for the username.
     * @param string $username   The username to be inserted into the format.
     *
     * @return string The formatted username.
     */
    public static function Format(string $rankFormat, string $username): string
    {
        return str_replace("{username}", $username, $rankFormat);
    }
}

?>