<?php

namespace App\Forum\Controllers;

class SubForumController
{
    static function GetLastPost($db, int $forumID, int $subforumID)
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

    static public function Create($db, int $forumID, string $subForumName, string $subForumDesc, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_subforums 
            (id,forum_id,subforum_name,subforum_desc,is_locked) 
            VALUES (?,?,?,?,?)',
            [0, $forumID, $subForumName, $subForumDesc, $isLocked ? 1 : 0]
        );
    }
}

?>