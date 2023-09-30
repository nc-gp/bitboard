<?php

namespace App\Classes;

use App\Forum\Structs\AccountStruct;

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
        $_SESSION['bitboard_user'] = new AccountStruct($userData['id'], $userData['username'], $userData['pass'], $userData['avatar'], $userData['reg_date'], $userData['reputation'], $userData['last_ip'], $userData['last_active'], $userData['last_login'], $userData['rank_id'], $userData['permissions']);
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
    static public function AddInformation(string $informationType, mixed $informationMessage, bool $instantSave = false, string $rgb = '255, 53, 53', int $duration = 5000, string $gravity = 'bottom', string $position = 'right'): void
    {
        $info = 'bb-info-' . $informationType;
        $_SESSION[$info]['msg'] = $informationMessage;
        $_SESSION[$info]['rgb'] = $rgb;
        $_SESSION[$info]['duration'] = $duration;
        $_SESSION[$info]['gravity'] = $gravity;
        $_SESSION[$info]['position'] = $position;

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