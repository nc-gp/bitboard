<?php

namespace App\Forum\Controllers;

class PostController
{
    public array $Data;
    private int $ID;

    public function __construct($db, int $id)
    {
        $this->ID = $id;
        $this->Data = $db->Query('SELECT * FROM bit_posts WHERE id = ? LIMIT 1', $this->ID)->FetchArray();
    }

    public function UpdateAuthor($db, int $newAuthorID)
    {
        $db->Query('UPDATE bit_posts SET user_id = ? WHERE id = ?', $newAuthorID, $this->ID);
    }

    public function UpdateThreadID($db, int $newThreadID)
    {
        $db->Query('UPDATE bit_posts SET thread_id = ? WHERE id = ?', $newThreadID, $this->ID);
    }

    public function UpdateContent($db, string $newPostContent)
    {
        $db->Query('UPDATE bit_posts SET post_content = ? WHERE id = ?', $newPostContent, $this->ID);
    }

    public function UpdateLikes($db, bool $addOrRemove = false)
    {
        $db->Query('UPDATE bit_posts SET post_likes = ? WHERE id = ?', $addOrRemove ? $this->Data['post_likes'] + 1 : $this->Data['post_likes'] - 1, $this->ID);
    }

    static public function Create($db, int $authorID, int $threadID, string $postContent)
    {
        $postTime = date("Y-m-d H:i:s");

        $db->Query('INSERT INTO bit_posts 
            (id,user_id,thread_id,post_content,post_likes,post_timestamp,post_edited_timestamp) 
            VALUES (?,?,?,?,?,?,?)',
            array(0, $authorID, $threadID, $postContent, 0, $postTime, $postTime)
        );
    }
}

?>