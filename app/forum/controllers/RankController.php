<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class RankController
{
    // Ranks created by the installer. Their names are referenced elsewhere
    // (for example registration looks up the "User" rank) and they cannot be
    // renamed or deleted from the admin panel.
    public const BUILT_IN_IDS = [1, 2, 3];

    public static function IsBuiltIn(int $rankID): bool
    {
        return in_array($rankID, self::BUILT_IN_IDS, true);
    }

    /**
     * Get every rank with its assigned user count for the admin panel.
     */
    public static function GetAll(Database $db): array
    {
        return $db->Query('SELECT r.*,
                (SELECT COUNT(*) FROM bit_accounts AS a WHERE a.rank_id = r.id) AS user_count
            FROM bit_ranks AS r
            ORDER BY r.id ASC')->FetchAll();
    }

    public static function GetByID(Database $db, int $rankID): array
    {
        return $db->Query('SELECT * FROM bit_ranks WHERE id = ? LIMIT 1', $rankID)->FetchArray();
    }

    public static function GetByName(Database $db, string $rankName): array
    {
        return $db->Query('SELECT * FROM bit_ranks WHERE rank_name = ? LIMIT 1', $rankName)->FetchArray();
    }

    public static function Update(Database $db, int $rankID, string $rankName, string $rankFormat, int $rankFlags): void
    {
        $db->Query('UPDATE bit_ranks SET rank_name = ?, rank_format = ?, rank_flags = ? WHERE id = ?',
            $rankName, $rankFormat, $rankFlags, $rankID);
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
        $db->Query('CREATE TABLE IF NOT EXISTS bit_ranks (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, rank_name VARCHAR(64) NOT NULL, rank_format VARCHAR(256) NOT NULL, rank_flags INT(6) NOT NULL)');
    }

    /**
     * Create inserts a new rank record into the 'bit_ranks' table.
     *
     * @param Database  db The database instance.
     * @param string    rankName The name of the rank.
     * @param string    rankFormat The format of the rank.
     * @param int       rankFlags Flags associated with the rank (default is 0).
     *
     * @return void
     */
    static public function Create(Database $db, string $rankName, string $rankFormat, int $rankFlags = 0)
    {
        $db->Query("INSERT INTO bit_ranks (id,rank_name,rank_format,rank_flags) VALUES (?,?,?,?)", [0, $rankName, $rankFormat, $rankFlags]);
    }
}

?>