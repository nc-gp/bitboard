<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class ThreadController
{
    static public function GetLastPost(Database $db, int $threadID)
    {
        return $db->Query('SELECT bit_posts.*, bit_accounts.avatar, bit_accounts.username, bit_accounts.rank_id, bit_ranks.rank_format
            FROM bit_posts
            LEFT JOIN bit_accounts ON bit_posts.user_id = bit_accounts.id
            LEFT JOIN bit_ranks ON bit_accounts.rank_id = bit_ranks.id
            WHERE bit_posts.thread_id = ?
            ORDER BY bit_posts.post_timestamp DESC
            LIMIT 1', 
            [$threadID]
        )->FetchArray();
    }

    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_threads (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			user_id INT(6) NOT NULL,
			thread_title VARCHAR(128) NOT NULL,
			thread_content VARCHAR(4096) NOT NULL,
			thread_likes INT(6) NOT NULL,
			thread_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			thread_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			forum_id INT(6) NOT NULL,
			subforum_id INT(6) NOT NULL,
			is_closed TINYINT(1) NOT NULL,
			is_pinned TINYINT(1) NOT NULL
		)');

        $db->Query('CREATE TABLE IF NOT EXISTS bit_threads_likes (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            reputation_type TINYINT(1) NOT NULL
        )');
    }

    static public function Create(Database $db, int $authorID, string $threadTitle, string $threadContent, bool $isClosed = false, bool $isPinned = false, int $forumID = -1, int $subForumID = -1)
    {
        $threadTime = date("Y-m-d H:i:s");

        $db->Query('INSERT INTO bit_threads 
            (id,user_id,thread_title,thread_content,thread_likes,thread_timestamp,thread_edited_timestamp,forum_id,subforum_id,is_closed,is_pinned) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [0, $authorID, $threadTitle, $threadContent, 0, $threadTime, $threadTime, $forumID, $subForumID, $isClosed ? 1 : 0, $isPinned ? 1 : 0]
        );
    }
}

?>