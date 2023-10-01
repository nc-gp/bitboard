<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class SubForumController
{
    /**
     * GetLastPost retrieves the last post in a subforum from the database.
     *
     * @param Database  db The database instance.
     * @param int       forumID The ID of the forum containing the subforum.
     * @param int       subforumID The ID of the subforum to retrieve the last post for.
     *
     * @return array    An associative array containing information about the last post in the subforum.
     */
    static function GetLastPost(Database $db, int $forumID, int $subforumID)
    {
        return $db->Query('SELECT p.post_timestamp, a.id, a.username, a.avatar, t.id AS thread_id, t.thread_title, r.rank_format FROM bit_posts AS p JOIN bit_threads AS t ON p.thread_id = t.id JOIN bit_accounts AS a ON p.user_id = a.id JOIN bit_ranks AS r ON a.rank_id = r.id WHERE t.forum_id = ? AND t.subforum_id = ? ORDER BY p.post_timestamp DESC LIMIT 1', [$forumID, $subforumID])->FetchArray();
    }

    /**
     * CreateTable creates the 'bit_subforums' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_subforums (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, forum_id INT(6) NOT NULL, subforum_name VARCHAR(128) NOT NULL, subforum_desc VARCHAR(256) NOT NULL, is_locked TINYINT(1) NOT NULL)');
    }

    /**
     * Create inserts a new subforum record into the 'bit_subforums' table.
     *
     * @param Database  db The database instance.
     * @param int       forumID The ID of the forum to which the subforum belongs.
     * @param string    subForumName The name of the subforum.
     * @param string    subForumDesc The description of the subforum.
     * @param bool      isLocked Indicates whether the subforum is locked (default is unlocked).
     *
     * @return void
     */
    static public function Create(Database $db, int $forumID, string $subForumName, string $subForumDesc, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_subforums (id,forum_id,subforum_name,subforum_desc,is_locked) VALUES (?,?,?,?,?)', [0, $forumID, $subForumName, $subForumDesc, $isLocked ? 1 : 0]);
    }
}

?>