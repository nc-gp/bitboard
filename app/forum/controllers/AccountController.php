<?php

namespace App\Forum\Controllers;

use App\Classes\Database;
use App\Classes\Permissions;

class AccountController
{
	private const ONLINE_WINDOW_SECONDS = 900;
	private static bool $schemaChecked = false;

	/**
	 * Add profile fields to installations created before they existed.
	 */
	static public function EnsureSchema(Database $db): void
	{
		if (self::$schemaChecked)
			return;

		self::$schemaChecked = true;

		try
		{
			$columns = $db->Query("SELECT column_name FROM information_schema.columns
				WHERE table_schema = DATABASE() AND table_name = 'bit_accounts'
					AND column_name IN ('bio', 'signature')")->FetchAll();
			$names = array_column($columns, 'column_name');

			if (!in_array('bio', $names, true))
				$db->Query("ALTER TABLE bit_accounts ADD COLUMN bio VARCHAR(1000) NOT NULL DEFAULT ''");

			if (!in_array('signature', $names, true))
				$db->Query("ALTER TABLE bit_accounts ADD COLUMN signature VARCHAR(500) NOT NULL DEFAULT ''");
		}
		catch (\Throwable $e)
		{
			error_log('BitBoard account schema check failed: ' . $e->getMessage());
		}
	}

	/**
	 * Get one account together with its rank name and flags.
	 */
	static public function GetWithRank(Database $db, int $userID): array
	{
		return $db->Query('SELECT a.*, r.rank_name, r.rank_flags, r.rank_position
			FROM bit_accounts AS a
			JOIN bit_ranks AS r ON r.id = a.rank_id
			WHERE a.id = ? LIMIT 1', $userID)->FetchArray();
	}

	private static function AdminUserWhere(string $search, int $rankID, array &$parameters): string
	{
		$where = 'a.id > 1';

		if ($search !== '')
		{
			$where .= ' AND (a.username LIKE ? OR a.email LIKE ?)';
			$parameters[] = '%' . $search . '%';
			$parameters[] = '%' . $search . '%';
		}

		if ($rankID > 0)
		{
			$where .= ' AND a.rank_id = ?';
			$parameters[] = $rankID;
		}

		return $where;
	}

	static public function CountAdminUsers(Database $db, string $search, int $rankID): int
	{
		$parameters = [];
		$where = self::AdminUserWhere($search, $rankID, $parameters);
		$sql = 'SELECT COUNT(*) AS user_count FROM bit_accounts AS a WHERE ' . $where;

		$row = count($parameters) > 0
			? $db->Query($sql, $parameters)->FetchArray()
			: $db->Query($sql)->FetchArray();

		return (int) ($row['user_count'] ?? 0);
	}

	static public function GetAdminUsers(Database $db, string $search, int $rankID, int $offset, int $limit): array
	{
		$parameters = [];
		$where = self::AdminUserWhere($search, $rankID, $parameters);
		$parameters[] = $offset;
		$parameters[] = $limit;

		return $db->Query('SELECT a.id, a.username, a.email, a.reg_date, a.reputation, a.last_active, a.rank_id,
				r.rank_name, r.rank_position,
				(SELECT COUNT(*) FROM bit_posts AS p WHERE p.user_id = a.id) AS post_count,
				(SELECT COUNT(*) FROM bit_threads AS t WHERE t.user_id = a.id) AS thread_count
			FROM bit_accounts AS a
			JOIN bit_ranks AS r ON r.id = a.rank_id
			WHERE ' . $where . '
			ORDER BY a.reg_date DESC, a.id DESC
			LIMIT ?, ?', $parameters)->FetchAll();
	}

	static public function SetRank(Database $db, int $userID, int $rankID): void
	{
		$db->Query('UPDATE bit_accounts SET rank_id = ? WHERE id = ?', $rankID, $userID);
	}

	static public function SetReputation(Database $db, int $userID, int $reputation): void
	{
		$db->Query('UPDATE bit_accounts SET reputation = ? WHERE id = ?', $reputation, $userID);
	}

	/**
	 * Count real accounts (excluding the system account and the target) whose
	 * rank still grants admin panel access.
	 */
	static public function CountWithAdminAccessExcept(Database $db, int $excludeUserID): int
	{
		$row = $db->Query('SELECT COUNT(*) AS admin_count
			FROM bit_accounts AS a
			JOIN bit_ranks AS r ON r.id = a.rank_id
			WHERE a.id > 1 AND a.id <> ? AND (r.rank_flags & ?) <> 0',
			$excludeUserID, Permissions::ADMIN_PANEL_ACCESS)->FetchArray();

		return (int) ($row['admin_count'] ?? 0);
	}

	/**
     * GetAccountByLogin retrieves account information by username or email.
     *
     * @param Database 	db The database instance.
     * @param string 	login The username or email address to search for.
     *
     * @return array 	An associative array containing account information.
     */
	static public function GetAccountByLogin(Database $db, string $login): array
	{
		return $db->Query('SELECT * FROM bit_accounts WHERE username = ? OR email = ? LIMIT 1', $login, $login)->FetchArray();
	}

	/**
	 * Get the timestamp used to decide whether an account is online.
	 */
	private static function OnlineCutoff(): string
	{
		return date('Y-m-d H:i:s', time() - self::ONLINE_WINDOW_SECONDS);
	}

	/**
	 * Count accounts active inside the online window.
	 */
	static public function CountOnline(Database $db): int
	{
		$count = $db->Query('SELECT COUNT(*) AS online_count FROM bit_accounts
			WHERE id > 1 AND last_active >= ?', self::OnlineCutoff())->FetchArray();

		return (int) ($count['online_count'] ?? 0);
	}

	/**
	 * Get accounts active inside the online window.
	 *
	 * @param Database db The database instance.
	 * @param int      offset The number of rows to skip.
	 * @param int      limit The maximum number of rows.
	 *
	 * @return array The online account rows.
	 */
	static public function GetOnline(Database $db, int $offset, int $limit): array
	{
		return $db->Query('SELECT a.id, a.username, a.avatar, a.reg_date, a.reputation, a.last_active, r.rank_name, r.rank_format
			FROM bit_accounts AS a
			JOIN bit_ranks AS r ON r.id = a.rank_id
			WHERE a.id > 1 AND a.last_active >= ?
			ORDER BY a.last_active DESC, a.username ASC
			LIMIT ?, ?', self::OnlineCutoff(), $offset, $limit)->FetchAll();
	}

	/**
     * UpdateLastActive updates the last active timestamp for the specified user.
     *
     * @param Database 	db The database instance.
     * @param int 		userId The ID of the user to update.
     *
     * @return void
     */
    static public function UpdateLastActive(Database $db, int $userId): void
    {
        try
        {
            $db->Query('UPDATE bit_accounts SET last_active = ? WHERE id = ?', date("Y-m-d H:i:s"), $userId);
        }
        catch (\mysqli_sql_exception $e)
        {
            // 1205 = lock wait timeout, 1213 = deadlock. Last-active is
            // non-critical, so do not take the whole page down for these.
            $code = (int) $e->getCode();
            if ($code !== 1205 && $code !== 1213)
                throw $e;

            error_log('BitBoard: unable to update last_active for account ' . $userId . ': ' . $e->getMessage());
        }
    }

	/**
     * CreateTable creates the 'bit_accounts' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_accounts ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL, pass VARCHAR(1024) NOT NULL, email VARCHAR(64) NOT NULL, avatar VARCHAR(255) NULL DEFAULT NULL, bio VARCHAR(1000) NOT NULL DEFAULT \'\', signature VARCHAR(500) NOT NULL DEFAULT \'\', reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, reputation INT(6) NOT NULL DEFAULT 0, last_ip VARCHAR(64), last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP, rank_id INT(6) NOT NULL)');
    }

	/**
     * Create inserts a new account record into the 'bit_accounts' table.
     *
     * @param Database 	db The database instance.
     * @param string 	username The username for the new account.
     * @param string 	hashedPassword The hashed password for the new account.
     * @param string 	email The email for the new account.
     * @param int 		rankId The ID of the rank for the new account.
     *
     * @return void
     */
    static public function Create(Database $db, string $username, string $hashedPassword, string $email, int $rankId)
    {
        $db->Query('INSERT INTO bit_accounts (id,username,pass,email,avatar,reg_date,reputation,last_ip,last_active,last_login,rank_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [0, $username, $hashedPassword, $email, 'default.webp', date("Y-m-d H:i:s"), 0, $_SERVER['REMOTE_ADDR'], date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $rankId]);
    }
}

?>