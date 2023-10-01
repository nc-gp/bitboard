<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class AccountController
{
	/**
     * GetAccountByName retrieves account information based on the provided username.
     *
     * @param Database 	db The database instance.
     * @param string 	userName The username to search for.
     *
     * @return array 	An associative array containing account information.
     */
	static public function GetAccountByName(Database $db, string $userName): array
	{
		return $db->Query('SELECT * FROM bit_accounts WHERE username = ? LIMIT 1', $userName)->FetchArray();
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
        $db->Query('UPDATE bit_accounts SET last_active = ? WHERE id = ?', date("Y-m-d H:i:s"), $userId);
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
        $db->Query('CREATE TABLE IF NOT EXISTS bit_accounts ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL, pass VARCHAR(1024) NOT NULL, email VARCHAR(64) NOT NULL, avatar VARCHAR(255) NULL DEFAULT NULL, reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, reputation INT(6) NOT NULL DEFAULT 0, last_ip VARCHAR(64), last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP, rank_id INT(6) NOT NULL)');
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