<?php

namespace App\Forum\Controllers;

class ForumController
{
    public array $Data;
    private int $ID;

    public function __construct($db, int $id)
    {
        $this->ID = $id;
        $this->Data = $db->Query('SELECT * FROM bit_forums WHERE id = ?', $this->ID)->FetchArray();
    }

    public function UpdateCategoryID($db, int $newCategoryID)
    {
        $db->Query('UPDATE bit_forums SET category_id = ? WHERE id = ?', [$newCategoryID, $this->ID]);
    }

    public function UpdateName($db, string $newForumName)
    {
        $db->Query('UPDATE bit_forums SET forum_name = ? WHERE id = ?', [$newForumName, $this->ID]);
    }

    public function UpdateDescription($db, string $newForumDescription)
    {
        $db->Query('UPDATE bit_forums SET forum_desc = ? WHERE id = ?', [$newForumDescription, $this->ID]);
    }

    public function UpdateLock($db, bool $newForumLock)
    {
        $db->Query('UPDATE bit_forums SET is_locked = ? WHERE id = ?', [$newForumLock ? 1 : 0, $this->ID]);
    }

    static public function GetLastPost($db, int $forumID)
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

    static public function Create($db, int $categoryID, string $forumName, string $forumDesc, string $forumIcon = '', int $forumPosition = 1, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_forums 
            (id,category_id,forum_name,forum_desc,forum_icon,forum_position,is_locked) 
            VALUES (?,?,?,?,?,?,?)',
            [0, $categoryID, $forumName, $forumDesc, $forumIcon, $forumPosition, $isLocked ? 1 : 0]
        );
    }
}

?>