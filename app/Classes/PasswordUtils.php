<?php

namespace App\Classes;

/**
 * The PasswordUtils class provides static methods for working with passwords.
 */
class PasswordUtils
{
    /**
     * Check if a password meets the minimum length requirement.
     *
     * @param string $password The password string to check.
     * @return bool True if the password meets the length requirement; false otherwise.
     */
    public static function CheckLength(string $password): bool
    {
        return strlen($password) >= 8;
    }

    /**
     * Check if a password contains at least one numeric digit.
     *
     * @param string $password The password string to check.
     * @return bool True if the password contains a numeric digit; false otherwise.
     */
    public static function CheckNumber(string $password): bool
    {
        return preg_match("#[0-9]+#", $password) === 1;
    }

    /**
     * Check if a password contains at least one letter (uppercase or lowercase).
     *
     * @param string $password The password string to check.
     * @return bool True if the password contains a letter; false otherwise.
     */
    public static function CheckLetter(string $password): bool
    {
        return preg_match("#[a-zA-Z]+#", $password) === 1;
    }

    /**
     * Get the bcrypt hash of a password.
     *
     * @param string $password The password string to hash.
     * @return string The bcrypt hash of the password.
     */
    public static function GetHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify if a provided hash matches a password.
     *
     * @param string $password The password string to verify.
     * @param string $currentHash The hash to compare with the password.
     * @return bool True if the hash matches the password; false otherwise.
     */
    public static function Verify(string $password, string $currentHash): bool
    {
        return password_verify($password, $currentHash);
    }
}


?>