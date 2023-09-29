<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class PostController
{
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_posts (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			user_id INT(6) NOT NULL,
			thread_id INT(6) NOT NULL,
			post_content VARCHAR(4096) NOT NULL,
			post_likes INT(6) NOT NULL,
			post_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			post_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		)');

        $db->Query('CREATE TABLE IF NOT EXISTS bit_posts_likes (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            post_id INT(6) NOT NULL,
            reputation_type TINYINT(1) NOT NULL
        )');
    }

    static public function Create(Database $db, int $authorID, int $threadID, string $postContent)
    {
        $db->Query('INSERT INTO bit_posts 
            (id,user_id,thread_id,post_content,post_likes,post_timestamp,post_edited_timestamp) 
            VALUES (?,?,?,?,?,?,?)',
            array(0, $authorID, $threadID, $postContent, 0, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"))
        );
    }
}

?>