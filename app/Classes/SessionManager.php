<?php

namespace App\Classes;

/**
 * The Session class provides methods for working with user sessions.
 */
class SessionManager
{
    /**
     * Check if a user is currently logged in.
     *
     * @return bool True if a user is logged in; false otherwise.
     */
    static public function IsLogged(): bool
    {
        return isset($_SESSION['bitboard_logged']);
    }

    /**
     * Check if a user is currently logged in.
     *
     * @param array $userData Account data.
     */
    static public function Set(array $userData): void
    {
        $_SESSION['bitboard_logged'] = true;
        $_SESSION['bitboard_user'] = $userData;
    }

    /**
     * Deletes the user session. (logout)
     */
    static public function Delete(): void
    {
        unset($_SESSION['bitboard_user']);
        unset($_SESSION['bitboard_logged']);
    }

    /**
     * Add information of a specific type to the session.
     *
     * @param string $informationType    The type of information to add.
     * @param mixed  $informationMessage The information message to store in the session.
     * @param bool   $instantSave        Saves and closes the seession instantly. (default: false)
     */
    static public function AddInformation(string $informationType, mixed $informationMessage, bool $instantSave = false): void
    {
        $_SESSION['bb-info-' . $informationType]['msg'] = $informationMessage;

        if($instantSave)
            session_write_close();
    }

    /**
     * Remove information of a specific type from the session.
     *
     * @param string $informationType The type of information to remove.
     */
    static public function RemoveInformation(string $informationType): void
    {
        unset($_SESSION['bb-info-' . $informationType]);
    }
}

?>