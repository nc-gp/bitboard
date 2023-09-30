<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class PrefixController
{
    public static function GetPrefixByID(Database $db, int $prefixID): array
    {
        return $db->Query('SELECT * FROM bit_prefixes WHERE id = ?', $prefixID)->FetchArray();
    }

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_prefixes (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, prefix_name VARCHAR(64) NOT NULL, prefix_class VARCHAR(64) NOT NULL, is_removable TINYINT(1) NOT NULL)');
    }

    public static function Create(Database $db, string $prefixName, string $prefixClass, int $isRemovable = 0): void
    {
        $db->Query('INSERT INTO bit_prefixes (id,prefix_name,prefix_class,is_removable) VALUES (?,?,?,?)', [0, $prefixName, $prefixClass, $isRemovable]);
    }
}

?>