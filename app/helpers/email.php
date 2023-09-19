<?php

/**
 * The Email class provides methods for working with email addresses.
 */
class Email
{
    public string $String;

    /**
     * Constructor to initialize an Email object with an email address.
     *
     * @param string $string The email address to validate.
     */
    public function __construct(string $string)
    {
        $this->String = $string;
    }

    /**
     * Validate the email address.
     *
     * @return bool True if the email address is valid; false otherwise.
     */
    public function Validate()
    {
        if (!filter_var($this->String, FILTER_VALIDATE_EMAIL))
            return false;

        if (!checkdnsrr($this->String, 'MX'))
            return false;

        return true;
    }
}

?>