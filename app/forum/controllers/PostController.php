<?php

namespace App\Forum\Controllers;

class PostController
{
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