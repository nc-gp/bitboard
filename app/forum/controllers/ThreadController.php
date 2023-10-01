<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class ThreadController
{
    /**
     * GetLastPost retrieves the last post in a thread from the database.
     *
     * @param Database  db The database instance.
     * @param int       threadID The ID of the thread to retrieve the last post for.
     *
     * @return array    An associative array containing information about the last post.
     */
    static public function GetLastPost(Database $db, int $threadID)
    {
        return $db->Query('SELECT bit_posts.*, bit_accounts.avatar, bit_accounts.username, bit_accounts.rank_id, bit_ranks.rank_format FROM bit_posts LEFT JOIN bit_accounts ON bit_posts.user_id = bit_accounts.id LEFT JOIN bit_ranks ON bit_accounts.rank_id = bit_ranks.id WHERE bit_posts.thread_id = ? ORDER BY bit_posts.post_timestamp DESC LIMIT 1', [$threadID])->FetchArray();
    }

    /**
     * CreateTable creates the 'bit_threads' and 'bit_threads_likes' tables in the database if they do not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_threads (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(6) NOT NULL, thread_title VARCHAR(128) NOT NULL, thread_content VARCHAR(4096) NOT NULL, thread_likes INT(6) NOT NULL, thread_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, thread_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, forum_id INT(6) NOT NULL, subforum_id INT(6) NOT NULL, is_closed TINYINT(1) NOT NULL, is_pinned TINYINT(1) NOT NULL, prefix_id INT(6) NOT NULL)');

        $db->Query('CREATE TABLE IF NOT EXISTS bit_threads_likes (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(6) NOT NULL, thread_id INT(6) NOT NULL,)');
    }

    /**
     * Create inserts a new thread record into the 'bit_threads' table.
     *
     * @param Database  db The database instance.
     * @param int       authorID The ID of the thread author.
     * @param string    threadTitle The title of the thread.
     * @param string    threadContent The content of the thread.
     * @param bool      isClosed Indicates whether the thread is closed (default is open).
     * @param bool      isPinned Indicates whether the thread is pinned (default is not pinned).
     * @param int       prefixID The ID of the thread prefix (default is 0).
     * @param int       forumID The ID of the forum where the thread belongs (default is -1).
     * @param int       subForumID The ID of the subforum where the thread belongs (default is -1).
     *
     * @return void
     */
    static public function Create(Database $db, int $authorID, string $threadTitle, string $threadContent, bool $isClosed = false, bool $isPinned = false, int $prefixID = 0, int $forumID = -1, int $subForumID = -1)
    {
        $db->Query('INSERT INTO bit_threads (id,user_id,thread_title,thread_content,thread_likes,thread_timestamp,thread_edited_timestamp,forum_id,subforum_id,is_closed,is_pinned,prefix_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [0, $authorID, $threadTitle, $threadContent, 0, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $forumID, $subForumID, $isClosed ? 1 : 0, $isPinned ? 1 : 0, $prefixID]);
    }
}

?>