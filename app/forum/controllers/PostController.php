<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class PostController
{
    /**
     * CreateTable creates the 'bit_posts' and 'bit_posts_likes' tables in the database if they do not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_posts (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(6) NOT NULL, thread_id INT(6) NOT NULL, post_content VARCHAR(4096) NOT NULL, post_likes INT(6) NOT NULL, post_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, post_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');

        $db->Query('CREATE TABLE IF NOT EXISTS bit_posts_likes (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(6) NOT NULL, post_id INT(6) NOT NULL)');
    }

    /**
     * Create inserts a new post record into the 'bit_posts' table.
     *
     * @param Database  db The database instance.
     * @param int       authorID The ID of the post author.
     * @param int       threadID The ID of the thread to which the post belongs.
     * @param string    postContent The content of the post.
     *
     * @return void
     */
    static public function Create(Database $db, int $authorID, int $threadID, string $postContent)
    {
        $db->Query('INSERT INTO bit_posts (id,user_id,thread_id,post_content,post_likes,post_timestamp,post_edited_timestamp) VALUES (?,?,?,?,?,?,?)', array(0, $authorID, $threadID, $postContent, 0, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")));
    }
}

?>