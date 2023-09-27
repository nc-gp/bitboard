<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class RankController
{
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