<?php

namespace App\Classes;

use App\Forum\Structs\AccountStruct;

/**
 * The Session class provides methods for working with user sessions.
 */
class SessionManager
{
    /**
     * Maximum time between two requests before the session is logged out.
     */
    public const IDLE_TIMEOUT = 1800; // 30 minutes

    /**
     * Maximum lifetime of a session, regardless of activity.
     */
    public const ABSOLUTE_TIMEOUT = 43200; // 12 hours

    /**
     * Start the session with hardened cookie settings.
     */
    static public function Start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            self::EnforceTimeouts();
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) max(self::IDLE_TIMEOUT, self::ABSOLUTE_TIMEOUT));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
        self::EnforceTimeouts();
    }

    /**
     * Log out a session that has exceeded its idle or absolute lifetime.
     */
    private static function EnforceTimeouts(): void
    {
        if (!isset($_SESSION['bitboard_logged']))
            return;

        $now = time();
        $loginTime = self::ReadTimestamp('bb_login_time', $now);
        $lastActivity = self::ReadTimestamp('bb_last_activity', $now);

        if (($now - $loginTime) >= self::ABSOLUTE_TIMEOUT || ($now - $lastActivity) >= self::IDLE_TIMEOUT)
        {
            self::Expire();
            return;
        }

        $_SESSION['bb_login_time'] = $loginTime;
        $_SESSION['bb_last_activity'] = $now;
    }

    /**
     * Read a positive UNIX timestamp, falling back to the current time.
     */
    private static function ReadTimestamp(string $key, int $now): int
    {
        $value = $_SESSION[$key] ?? null;

        if (is_int($value))
            $timestamp = $value;
        elseif (is_string($value) && ctype_digit($value))
            $timestamp = (int) $value;
        else
            return $now;

        if ($timestamp <= 0 || $timestamp > $now)
            return $now;

        return $timestamp;
    }

    /**
     * Clear the authenticated session and leave a login notice.
     */
    private static function Expire(): void
    {
        $_SESSION = [];

        // Rotate the identifier so an expired session cannot be reused.
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent())
            session_regenerate_id(true);

        $_SESSION['bb-info-login']['msg'] = 'Your session expired. Please log in again.';
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
     * Check whether the current session belongs to a user with a permission.
     *
     * @param int $permission The permission bit to check.
     * @return bool True when a logged-in user has the permission.
     */
    static public function HasPermission(int $permission): bool
    {
        return self::IsLogged()
            && isset($_SESSION['bitboard_user'])
            && is_object($_SESSION['bitboard_user'])
            && method_exists($_SESSION['bitboard_user'], 'HasPermission')
            && $_SESSION['bitboard_user']->HasPermission($permission);
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

        $now = time();
        $_SESSION['bb_login_time'] = $now;
        $_SESSION['bb_last_activity'] = $now;
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

        // This method is also used to refresh account data, so never reset the timers.
        if (!isset($_SESSION['bb_login_time']))
            $_SESSION['bb_login_time'] = time();

        if (!isset($_SESSION['bb_last_activity']))
            $_SESSION['bb_last_activity'] = time();
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