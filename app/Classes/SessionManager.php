<?php

namespace App\Classes;

use App\Forum\Structs\AccountStruct;

/**
 * The Session class provides methods for working with user sessions.
 */
class SessionManager
{
    /**
     * Start the session with hardened cookie settings.
     */
    static public function Start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            return;

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

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
     * Get the CSRF token for the current session, creating it when needed.
     *
     * @return string The session CSRF token.
     */
    static public function CsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            self::Start();

        if (empty($_SESSION['bb_csrf_token']) || !is_string($_SESSION['bb_csrf_token']))
            $_SESSION['bb_csrf_token'] = bin2hex(random_bytes(32));

        return $_SESSION['bb_csrf_token'];
    }

    /**
     * Validate a CSRF token against the one stored in the session.
     *
     * @param mixed $token The token submitted by the client.
     * @return bool True when the token is valid.
     */
    static public function ValidateCsrfToken(mixed $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            return false;

        if (!isset($_SESSION['bb_csrf_token']) || !is_string($_SESSION['bb_csrf_token']) || !is_string($token))
            return false;

        return hash_equals($_SESSION['bb_csrf_token'], $token);
    }

    /**
     * Replace the CSRF token with a fresh one.
     */
    static public function RotateCsrfToken(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            self::Start();

        $_SESSION['bb_csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Authenticate the user and regenerate the session ID.
     *
     * @param array $userData Account data.
     */
    static public function Login(array $userData): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            self::Start();

        if (!session_regenerate_id(true))
            throw new \RuntimeException('Unable to regenerate the session ID.');

        self::Set($userData);
        self::RotateCsrfToken();
    }

    /**
     * Store the authenticated user in the session.
     *
     * @param array $userData Account data.
     */
    static public function Set(array $userData): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
            self::Start();

        $_SESSION['bitboard_logged'] = true;
        $_SESSION['bitboard_user'] = new AccountStruct($userData);
    }

    /**
     * Deletes the user session. (logout)
     */
    static public function Delete(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE)
            return;

        $params = session_get_cookie_params();

        if (ini_get('session.use_cookies'))
        {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }

        session_destroy();
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
        $info = 'bb-info-' . $informationType;
        $_SESSION[$info]['msg'] = $informationMessage;

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