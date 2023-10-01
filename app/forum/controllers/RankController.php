<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class RankController
{
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
     * @param int       rankFlags Flags associated with the rank (default is 0000000000).
     *
     * @return void
     */
    static public function Create(Database $db, string $rankName, string $rankFormat, int $rankFlags = 0000000000)
    {
        $db->Query("INSERT INTO bit_ranks (id,rank_name,rank_format,rank_flags) VALUES (?,?,?,?)", [0, $rankName, $rankFormat, $rankFlags]);
    }
}

?>