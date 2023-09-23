<?php

namespace App\Classes;

/**
 * The EmailUtils class provides static methods for working with email addresses.
 */
class EmailUtils
{
    /**
     * Validate an email address.
     *
     * @param string $email The email address to validate.
     * @return bool True if the email address is valid; false otherwise.
     */
    public static function Validate(string $email): bool
    {
        // Use a simple regex for basic email validation
        return preg_match('/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/', $email) === 1;
    }

}

?>