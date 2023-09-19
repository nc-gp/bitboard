<?php

class RankController
{
    public array $Data;
    public bool $IsGood = true;
    private int $ID;
    

    public function __construct($db, int $rankId)
    {
        $this->ID = $rankId;
        $this->Data = $db->Query('SELECT * FROM bit_ranks WHERE id = ?', $this->ID)->FetchArray();

        if(sizeof($this->Data) <= 0)
        {
            $this->IsGood = false;
            Log::Error('Couldn\'t fetch rank with ID ' . $this->ID);
        }
    }

    public function UpdateName($db, string $newRankName)
    {
        $db->Query('UPDATE bit_ranks SET rank_name = ? WHERE id = ?', [$newRankName, $this->ID]);
    }

    public function UpdateFormat($db, string $newRankFormat)
    {
        $db->Query('UPDATE bit_ranks SET rank_format = ? WHERE id = ?', [$newRankFormat, $this->ID]);
    }

    public function Delete($db)
    {
        $db->Query('DELETE FROM bit_ranks WHERE id = ?', $this->ID);
    }

    static public function Create($db, string $rankName, string $rankFormat, int $rankFlags = 0000000000)
    {
        $db->Query("INSERT INTO bit_ranks 
            (id,rank_name,rank_format,rank_flags) 
            VALUES (?,?,?,?)", 
            [0, $rankName, $rankFormat, $rankFlags]
        );
    }
}

?>