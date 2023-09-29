<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class RankController
{
    static public function GetRankNameByID(Database $db, int $rankID): string
    {
        return $db->Query('SELECT rank_name FROM bit_ranks WHERE id = ?', $rankID)->FetchArray()['rank_name'];
    }

    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_ranks (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			rank_name VARCHAR(64) NOT NULL,
			rank_format VARCHAR(256) NOT NULL,
			rank_flags INT(6) NOT NULL
		)');
    }

    static public function Create(Database $db, string $rankName, string $rankFormat, int $rankFlags = 0000000000)
    {
        $db->Query("INSERT INTO bit_ranks 
            (id,rank_name,rank_format,rank_flags) 
            VALUES (?,?,?,?)", 
            [0, $rankName, $rankFormat, $rankFlags]
        );
    }
}

?>