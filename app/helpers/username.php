<?php

/**
 * The Username class provides methods for working with user usernames.
 */
class Username
{
    public string $String;

    /**
     * Constructor to initialize a Username object with a username string.
     *
     * @param string $string The username string to work with.
     */
    public function __construct(string $string)
    {
        $this->String = $string;
    }

    /**
     * Validate the username length.
     *
     * @return bool True if the username meets the length requirement; false otherwise.
     */
    public function Validate()
    {
        if (strlen($this->String) < 4)
            return false;

        return true;
    }

    /**
     * Format a username using a provided rank format.
     *
     * @param string $rankFormat The format string with "{username}" as a placeholder for the username.
     * @param string $username   The username to be inserted into the format.
     *
     * @return string The formatted username.
     */
    static public function Format(string $rankFormat, string $username): string
    {
        return str_replace("{username}", $username, $rankFormat);
    }
}

?>