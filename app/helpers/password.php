<?php

/**
 * The Password class provides methods for working with passwords.
 */
class Password
{
    public string $String;

    /**
     * Constructor to initialize a Password object with a password string.
     *
     * @param string $string The password string to work with.
     */
    public function __construct(string $string)
    {
        $this->String = $string;
    }

    /**
     * Check if the password meets the minimum length requirement.
     *
     * @return bool True if the password meets the length requirement; false otherwise.
     */
    public function CheckLength(): bool
    {
        if (strlen($this->String) < 8)
            return false;

        return true;
    }

    /**
     * Check if the password contains at least one numeric digit.
     *
     * @return bool True if the password contains a numeric digit; false otherwise.
     */
    public function CheckNumber(): bool
    {
        if (!preg_match("#[0-9]+#", $this->String))
            return false;
        
        return true;
    }

    /**
     * Check if the password contains at least one letter (uppercase or lowercase).
     *
     * @return bool True if the password contains a letter; false otherwise.
     */
    public function CheckLetter(): bool
    {
        if (!preg_match("#[a-zA-Z]+#", $this->String))
            return false;

        return true;
    }

    /**
     * Get the bcrypt hash of the password.
     *
     * @return string The bcrypt hash of the password.
     */
    public function GetHash()
    {
        return password_hash($this->String, PASSWORD_BCRYPT);
    }

    /**
     * Verify if the provided hash matches the password.
     *
     * @param string $CurrentHash The hash to compare with the password.
     * @return bool True if the hash matches the password; false otherwise.
     */
    public function Verify(string $CurrentHash): bool
    {
        return password_verify($this->String, $CurrentHash);
    }
}

?>