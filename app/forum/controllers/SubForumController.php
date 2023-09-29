<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class SubForumController
{
    static function GetLastPost(Database $db, int $forumID, int $subforumID)
    {
        return $db->Query('SELECT p.post_timestamp, a.id, a.username, a.avatar, t.id AS thread_id, t.thread_title, r.rank_format 
            FROM bit_posts AS p 
            JOIN bit_threads AS t ON p.thread_id = t.id 
            JOIN bit_accounts AS a ON p.user_id = a.id 
            JOIN bit_ranks AS r ON a.rank_id = r.id 
            WHERE t.forum_id = ? AND t.subforum_id = ?
            ORDER BY p.post_timestamp DESC 
            LIMIT 1',
            [$forumID, $subforumID]
        )->FetchArray();
    }

    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_subforums (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			forum_id INT(6) NOT NULL,
			subforum_name VARCHAR(128) NOT NULL,
			subforum_desc VARCHAR(256) NOT NULL,
			is_locked TINYINT(1) NOT NULL
		)');
    }

    static public function Create(Database $db, int $forumID, string $subForumName, string $subForumDesc, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_subforums 
            (id,forum_id,subforum_name,subforum_desc,is_locked) 
            VALUES (?,?,?,?,?)',
            [0, $forumID, $subForumName, $subForumDesc, $isLocked ? 1 : 0]
        );
    }
}

?>