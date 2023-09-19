<?php

class ThreadController
{
    public array $Data;
    private int $ID;

    public function __construct($db, int $id)
    {
        $this->ID = $id;
        $this->Data = $db->Query('SELECT * FROM bit_threads WHERE id = ? LIMIT 1', $this->ID)->FetchArray();
    }

    public function UpdateAuthor($db, int $newAuthorID)
    {
        $db->Query('UPDATE bit_threads SET user_id = ? WHERE id = ?', [$newAuthorID, $this->ID]);
    }

    public function UpdateForumID($db, int $newForumID)
    {
        $db->Query('UPDATE bit_threads SET forum_id = ? WHERE id = ?', [$newForumID, $this->ID]);
    }

    public function UpdateSubForumID($db, int $newSubForumID)
    {
        $db->Query('UPDATE bit_threads SET subforum_id = ? WHERE id = ?', [$newSubForumID, $this->ID]);
    }

    public function UpdateTitle($db, string $newThreadTitle)
    {
        $db->Query('UPDATE bit_threads SET thread_title = ? WHERE id = ?', [$newThreadTitle, $this->ID]);
    }

    public function UpdateContent($db, string $newThreadContent)
    {
        $db->Query('UPDATE bit_threads SET thread_content = ? WHERE id = ?', [$newThreadContent, $this->ID]);
    }

    public function UpdateLikes($db, bool $addOrRemove = false)
    {
        $db->Query('UPDATE bit_threads SET thread_likes = ? WHERE id = ?', [$addOrRemove ? $this->Data['thread_likes'] + 1 : $this->Data['thread_likes'] - 1, $this->ID]);
    }

    public function UpdateClosed($db, bool $isClosed)
    {
        $db->Query('UPDATE bit_threads SET is_closed = ? WHERE id = ?', [$isClosed ? 1 : 0, $this->ID]);
    }

    public function UpdatePinned($db, bool $isPinned)
    {
        $db->Query('UPDATE bit_threads SET is_pinned = ? WHERE id = ?', [$isPinned ? 1 : 0, $this->ID]);
    }

    static public function GetLastPost($db, int $threadID)
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

    static public function Create($db, int $authorID, string $threadTitle, string $threadContent, bool $isClosed = false, bool $isPinned = false, int $forumID = -1, int $subForumID = -1)
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