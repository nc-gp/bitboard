<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class PrefixController
{
    /**
     * GetPrefixByID retrieves prefix information from the database based on the provided prefix ID.
     *
     * @param Database  db The database instance.
     * @param int       prefixID The ID of the prefix to retrieve.
     *
     * @return array    An associative array containing prefix information.
     */
    public static function GetPrefixByID(Database $db, int $prefixID): array
    {
        return $db->Query('SELECT * FROM bit_prefixes WHERE id = ?', $prefixID)->FetchArray();
    }

    /**
     * CreateTable creates the 'bit_prefixes' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_prefixes (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, prefix_name VARCHAR(64) NOT NULL, prefix_class VARCHAR(64) NOT NULL, is_removable TINYINT(1) NOT NULL)');
    }

    /**
     * Create inserts a new prefix record into the 'bit_prefixes' table.
     *
     * @param Database  db The database instance.
     * @param string    prefixName The name of the prefix.
     * @param string    prefixClass The class associated with the prefix.
     * @param int       isRemovable Indicates whether the prefix is removable (default is not removable).
     *
     * @return void
     */
    public static function Create(Database $db, string $prefixName, string $prefixClass, int $isRemovable = 0): void
    {
        $db->Query('INSERT INTO bit_prefixes (id,prefix_name,prefix_class,is_removable) VALUES (?,?,?,?)', [0, $prefixName, $prefixClass, $isRemovable]);
    }
}

?>