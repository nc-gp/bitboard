<?php

namespace App\Forum\Controllers;

class SubForumController
{
    public array $Data;
    private int $ID;

    public function __construct($db, int $id)
    {
        $this->ID = $id;
        $this->Data = $db->Query('SELECT * FROM bit_subforums WHERE id = ?', $this->ID)->FetchArray();
    }

    public function UpdateForumID($db, int $newForumID)
    {
        $db->Query('UPDATE bit_subforums SET forum_id = ? WHERE id = ?', [$newForumID, $this->ID]);
    }

    public function UpdateName($db, string $newForumName)
    {
        $db->Query('UPDATE bit_subforums SET subforum_name = ? WHERE id = ?', [$newForumName, $this->ID]);
    }

    public function UpdateDescription($db, string $newForumDescription)
    {
        $db->Query('UPDATE bit_subforums SET subforum_desc = ? WHERE id = ?', [$newForumDescription, $this->ID]);
    }

    public function UpdateLock($db, bool $newForumLock)
    {
        $db->Query('UPDATE bit_subforums SET is_locked = ? WHERE id = ?', [$newForumLock ? 1 : 0, $this->ID]);
    }

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