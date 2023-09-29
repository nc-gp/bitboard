<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class ForumController
{
    static public function GetLastPost(Database $db, int $forumID)
    {
        return $db->Query('SELECT p.post_timestamp, a.id, a.username, a.avatar, t.id AS thread_id, t.thread_title, r.rank_format 
            FROM bit_posts AS p 
            JOIN bit_threads AS t ON p.thread_id = t.id 
            JOIN bit_accounts AS a ON p.user_id = a.id 
            JOIN bit_ranks AS r ON a.rank_id = r.id 
            WHERE t.forum_id = ? 
            ORDER BY p.post_timestamp DESC 
            LIMIT 1',
            $forumID
        )->FetchArray();
    }

    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_forums (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			category_id INT(6) NOT NULL,
			forum_name VARCHAR(128) NOT NULL,
			forum_desc VARCHAR(1024) NOT NULL,
			forum_icon VARCHAR(512) NOT NULL,
			forum_position INT(6) NOT NULL,
			is_locked TINYINT(1) NOT NULL
		)');
    }

    static public function Create(Database $db, int $categoryID, string $forumName, string $forumDesc, string $forumIcon = '', int $forumPosition = 1, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_forums 
            (id,category_id,forum_name,forum_desc,forum_icon,forum_position,is_locked) 
            VALUES (?,?,?,?,?,?,?)',
            [0, $categoryID, $forumName, $forumDesc, $forumIcon, $forumPosition, $isLocked ? 1 : 0]
        );
    }
}

?>