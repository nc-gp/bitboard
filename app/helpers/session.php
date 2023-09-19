<?php

/**
 * The Session class provides methods for working with user sessions.
 */
class Session
{
    /**
     * Check if a user is currently logged in.
     *
     * @return bool True if a user is logged in; false otherwise.
     */
    static public function IsLogged()
    {
        return isset($_SESSION['bitboard_logged']);
    }
}


?>