<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

class RankController
{
    // Ranks created by the installer. Their names are referenced elsewhere
    // (for example registration looks up the "User" rank) and they cannot be
    // renamed or deleted from the admin panel.
    public const BUILT_IN_IDS = [1, 2, 3];

    // Higher positions have more authority. The built-in Admin rank is always
    // the highest, and custom ranks can be placed between Banned and Admin.
    public const ADMIN_POSITION = 100;
    public const USER_POSITION = 50;
    public const BANNED_POSITION = 0;
    public const MAX_CUSTOM_POSITION = 99;

    private static bool $schemaChecked = false;

    public static function IsBuiltIn(int $rankID): bool
    {
        return in_array($rankID, self::BUILT_IN_IDS, true);
    }

    /**
     * Add rank_position to installations created before the hierarchy existed.
     */
    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $column = $db->Query("SELECT COUNT(*) AS column_count FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'bit_ranks' AND column_name = 'rank_position'")->FetchArray();

            if ((int) ($column['column_count'] ?? 0) === 1)
                return;

            $db->Query('ALTER TABLE bit_ranks ADD COLUMN rank_position INT(6) NOT NULL DEFAULT 0');
            $db->Query('UPDATE bit_ranks SET rank_position = ' . self::ADMIN_POSITION . ' WHERE id = 1');
            $db->Query('UPDATE bit_ranks SET rank_position = ' . self::USER_POSITION . ' WHERE id = 2');
            $db->Query('UPDATE bit_ranks SET rank_position = ' . self::BANNED_POSITION . ' WHERE id = 3');
            $db->Query('UPDATE bit_ranks SET rank_position = 10 WHERE id > 3 AND rank_position = 0');
        }
        catch (Throwable $e)
        {
            error_log('BitBoard rank schema check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get every rank with its assigned user count for the admin panel.
     */
    public static function GetAll(Database $db): array
    {
        return $db->Query('SELECT r.*,
                (SELECT COUNT(*) FROM bit_accounts AS a WHERE a.rank_id = r.id) AS user_count
            FROM bit_ranks AS r
            ORDER BY r.rank_position DESC, r.id ASC')->FetchAll();
    }

    public static function GetByID(Database $db, int $rankID): array
    {
        return $db->Query('SELECT * FROM bit_ranks WHERE id = ? LIMIT 1', $rankID)->FetchArray();
    }

    public static function GetByName(Database $db, string $rankName): array
    {
        return $db->Query('SELECT * FROM bit_ranks WHERE rank_name = ? LIMIT 1', $rankName)->FetchArray();
    }

    public static function MaxPosition(Database $db): int
    {
        $row = $db->Query('SELECT COALESCE(MAX(rank_position), 0) AS max_position
            FROM bit_ranks')->FetchArray();

        return (int) ($row['max_position'] ?? 0);
    }

    public static function Update(
        Database $db,
        int $rankID,
        string $rankName,
        string $rankFormat,
        int $rankFlags,
        int $rankPosition
    ): void
    {
        $db->Query('UPDATE bit_ranks
            SET rank_name = ?, rank_format = ?, rank_flags = ?, rank_position = ?
            WHERE id = ?',
            $rankName, $rankFormat, $rankFlags, $rankPosition, $rankID);
    }

    public static function Delete(Database $db, int $rankID): void
    {
        $db->Query('DELETE FROM bit_ranks WHERE id = ?', $rankID);
    }

    public static function CountUsers(Database $db, int $rankID): int
    {
        $row = $db->Query('SELECT COUNT(*) AS user_count FROM bit_accounts
            WHERE rank_id = ?', $rankID)->FetchArray();

        return (int) ($row['user_count'] ?? 0);
    }

    /**
     * GetRankNameByID retrieves the rank name from the database based on the provided rank ID.
     *
     * @param Database  db The database instance.
     * @param int       rankID The ID of the rank to retrieve.
     *
     * @return string   The name of the rank.
     */
    static public function GetRankNameByID(Database $db, int $rankID): string
    {
        return $db->Query('SELECT rank_name FROM bit_ranks WHERE id = ?', $rankID)->FetchArray()['rank_name'];
    }

    /**
     * CreateTable creates the 'bit_ranks' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_ranks (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rank_name VARCHAR(64) NOT NULL,
            rank_format VARCHAR(256) NOT NULL,
            rank_flags INT(6) NOT NULL,
            rank_position INT(6) NOT NULL DEFAULT 0
        )');
    }

    /**
     * Create inserts a new rank record into the 'bit_ranks' table.
     *
     * @param Database  db The database instance.
     * @param string    rankName The name of the rank.
     * @param string    rankFormat The format of the rank.
     * @param int       rankFlags Flags associated with the rank (default is 0).
     * @param int       rankPosition Hierarchy position; higher values have more authority.
     *
     * @return void
     */
    static public function Create(Database $db, string $rankName, string $rankFormat, int $rankFlags = 0, int $rankPosition = 0)
    {
        $db->Query("INSERT INTO bit_ranks (id,rank_name,rank_format,rank_flags,rank_position) VALUES (?,?,?,?,?)", [0, $rankName, $rankFormat, $rankFlags, $rankPosition]);
    }
}

?>