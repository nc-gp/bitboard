<?php

namespace App\Classes;

/**
 * Database-backed login throttling and failed-attempt logging.
 *
 * Limits are sliding windows: once the configured number of failures is reached,
 * new attempts are rejected until the oldest rows fall outside the window.
 */
class LoginThrottle
{
    /**
     * Sliding window used for both limits.
     */
    public const WINDOW = 900; // 15 minutes

    /**
     * Maximum failed attempts from one IP inside the window.
     */
    public const IP_MAX_ATTEMPTS = 10;

    /**
     * Maximum failed attempts for one account or unknown identifier inside the window.
     */
    public const ACCOUNT_MAX_ATTEMPTS = 5;

    /**
     * How long failed-attempt rows are kept for logging.
     */
    private const RETENTION = 604800; // 7 days

    private Database $db;
    private string $identifier;
    private string $ip;

    private static ?bool $tableAvailable = null;

    /**
     * @param Database $db The database connection.
     * @param string   $identifier The submitted username or email.
     * @param string   $ip The client IP address.
     */
    public function __construct(Database $db, string $identifier, string $ip)
    {
        $this->db = $db;
        $this->identifier = self::NormalizeIdentifier($identifier);
        $this->ip = self::NormalizeIp($ip);
    }

    /**
     * Create the failed-login table. The installer calls this for fresh installs;
     * existing installs also create it lazily on the first login attempt.
     */
    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_login_attempts (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT(6) UNSIGNED NOT NULL DEFAULT 0,
            ip VARCHAR(64) NOT NULL,
            identifier VARCHAR(64) NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_attempts_ip (ip, attempted_at),
            INDEX idx_login_attempts_account (account_id, attempted_at),
            INDEX idx_login_attempts_identifier (identifier, attempted_at),
            INDEX idx_login_attempts_time (attempted_at)
        )');
    }

    /**
     * Check whether this IP has reached the per-IP failure limit.
     */
    public function IsIpBlocked(): bool
    {
        if(!$this->EnsureTable())
            return false;

        try
        {
            $row = $this->db->Query(
                'SELECT COUNT(*) AS total FROM bit_login_attempts WHERE ip = ? AND attempted_at >= ?',
                $this->ip,
                self::WindowStart()
            )->FetchArray();

            return (int) ($row['total'] ?? 0) >= self::IP_MAX_ATTEMPTS;
        }
        catch (\Throwable $e)
        {
            $this->LogFailure('IP check', $e);
            return false;
        }
    }

    /**
     * Check whether this account, or this unknown identifier, has reached its limit.
     *
     * @param int $accountId The resolved account ID, or 0 when the login is unknown.
     */
    public function IsAccountBlocked(int $accountId = 0): bool
    {
        if(!$this->EnsureTable())
            return false;

        if($accountId <= 0 && $this->identifier === '')
            return false;

        try
        {
            if($accountId > 0)
            {
                $row = $this->db->Query(
                    'SELECT COUNT(*) AS total FROM bit_login_attempts WHERE account_id = ? AND attempted_at >= ?',
                    $accountId,
                    self::WindowStart()
                )->FetchArray();
            }
            else
            {
                $row = $this->db->Query(
                    'SELECT COUNT(*) AS total FROM bit_login_attempts WHERE account_id = 0 AND identifier = ? AND attempted_at >= ?',
                    $this->identifier,
                    self::WindowStart()
                )->FetchArray();
            }

            return (int) ($row['total'] ?? 0) >= self::ACCOUNT_MAX_ATTEMPTS;
        }
        catch (\Throwable $e)
        {
            $this->LogFailure('account check', $e);
            return false;
        }
    }

    /**
     * Log one failed login attempt.
     *
     * @param int $accountId The resolved account ID, or 0 when the login is unknown.
     */
    public function RecordFailure(int $accountId = 0): void
    {
        if(!$this->EnsureTable())
            return;

        try
        {
            $this->db->Query(
                'INSERT INTO bit_login_attempts (account_id, ip, identifier, attempted_at) VALUES (?, ?, ?, ?)',
                $accountId > 0 ? $accountId : 0,
                $this->ip,
                $this->identifier,
                date('Y-m-d H:i:s')
            );

            // Keep the log bounded. The rate-limit queries only use the recent window.
            $this->db->Query(
                'DELETE FROM bit_login_attempts WHERE attempted_at < ?',
                date('Y-m-d H:i:s', time() - self::RETENTION)
            );
        }
        catch (\Throwable $e)
        {
            $this->LogFailure('record failure', $e);
        }
    }

    /**
     * Clear the failures for a successfully authenticated account.
     *
     * The rows are removed from the shared failure log, so they also stop counting
     * toward the IP limit. Failures for other accounts from the same IP remain.
     *
     * @param int $accountId The authenticated account ID.
     */
    public function ClearFailures(int $accountId = 0): void
    {
        if(!$this->EnsureTable())
            return;

        if($accountId <= 0 && $this->identifier === '')
            return;

        try
        {
            if($accountId > 0)
            {
                $this->db->Query(
                    'DELETE FROM bit_login_attempts WHERE account_id = ? OR identifier = ?',
                    $accountId,
                    $this->identifier
                );
            }
            else
            {
                $this->db->Query(
                    'DELETE FROM bit_login_attempts WHERE account_id = 0 AND identifier = ?',
                    $this->identifier
                );
            }
        }
        catch (\Throwable $e)
        {
            $this->LogFailure('clear failures', $e);
        }
    }

    /**
     * Ensure the table exists once per request. If it cannot be created, fail open
     * instead of locking every account out because of a database privilege problem.
     */
    private function EnsureTable(): bool
    {
        if(self::$tableAvailable !== null)
            return self::$tableAvailable;

        try
        {
            // The installer creates this table for fresh installations. Existing
            // installations create it here on their first login attempt; checking
            // first avoids running DDL on every login.
            $table = $this->db->Query("SHOW TABLES LIKE 'bit\\_login\\_attempts'")->FetchArray();

            if(empty($table))
                self::CreateTable($this->db);

            self::$tableAvailable = true;
        }
        catch (\Throwable $e)
        {
            self::$tableAvailable = false;
            $this->LogFailure('table setup', $e);
        }

        return self::$tableAvailable;
    }

    private function LogFailure(string $operation, \Throwable $e): void
    {
        error_log('BitBoard: login throttle ' . $operation . ' failed for ' . $this->ip . ': ' . $e->getMessage());
    }

    private static function WindowStart(): string
    {
        return date('Y-m-d H:i:s', time() - self::WINDOW);
    }

    private static function NormalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if(function_exists('mb_substr'))
        {
            $identifier = mb_substr($identifier, 0, 64, 'UTF-8');
            $identifier = mb_strtolower($identifier, 'UTF-8');
            return mb_substr($identifier, 0, 64, 'UTF-8');
        }

        return strtolower(substr($identifier, 0, 64));
    }

    private static function NormalizeIp(string $ip): string
    {
        $ip = trim($ip);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }
}

?>
