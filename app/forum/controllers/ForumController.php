<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class ForumController
{
    /**
     * GetLastPost retrieves the last post in a forum from the database.
     *
     * @param Database  db The database instance.
     * @param int       forumID The ID of the forum to retrieve the last post for.
     *
     * @return array    An associative array containing information about the last post in the forum.
     */
    static public function GetLastPost(Database $db, int $forumID)
    {
        return $db->Query('SELECT p.post_timestamp, a.id, a.username, a.avatar, t.id AS thread_id, t.thread_title, r.rank_format FROM bit_posts AS p JOIN bit_threads AS t ON p.thread_id = t.id JOIN bit_accounts AS a ON p.user_id = a.id JOIN bit_ranks AS r ON a.rank_id = r.id WHERE t.forum_id = ? ORDER BY p.post_timestamp DESC LIMIT 1', $forumID)->FetchArray();
    }

    /**
     * GetLastPosts retrieves the last post for each requested forum in one query.
     *
     * @param Database db The database instance.
     * @param array forumIDs The forum IDs to retrieve last posts for.
     *
     * @return array The last post rows keyed by forum ID.
     */
    static public function GetLastPosts(Database $db, array $forumIDs): array
    {
        $forumIDs = array_values(array_unique(array_map('intval', $forumIDs)));

        if (count($forumIDs) <= 0)
            return [];

        $placeholders = implode(',', array_fill(0, count($forumIDs), '?'));
        $lastPosts = $db->Query('SELECT p.post_timestamp, a.id, a.username, a.avatar, t.id AS thread_id, t.thread_title, t.forum_id, r.rank_format
            FROM bit_posts AS p
            JOIN bit_threads AS t ON p.thread_id = t.id
            JOIN bit_accounts AS a ON p.user_id = a.id
            JOIN bit_ranks AS r ON a.rank_id = r.id
            WHERE t.forum_id IN (' . $placeholders . ')
              AND p.id = (
                  SELECT p2.id
                  FROM bit_posts AS p2
                  JOIN bit_threads AS t2 ON p2.thread_id = t2.id
                  WHERE t2.forum_id = t.forum_id
                  ORDER BY p2.post_timestamp DESC, p2.id DESC
                  LIMIT 1
              )', $forumIDs)->FetchAll();

        $result = [];
        foreach ($lastPosts as $lastPost)
            $result[(int) $lastPost['forum_id']] = $lastPost;

        return $result;
    }

    /**
     * CreateTable creates the 'bit_forums' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_forums (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT(6) NOT NULL, forum_name VARCHAR(128) NOT NULL, forum_desc VARCHAR(1024) NOT NULL, forum_icon VARCHAR(512) NOT NULL, forum_position INT(6) NOT NULL, is_locked TINYINT(1) NOT NULL)');
    }

    /**
     * Create inserts a new forum record into the 'bit_forums' table.
     *
     * @param Database  db The database instance.
     * @param int       categoryID The ID of the category to which the forum belongs.
     * @param string    forumName The name of the forum.
     * @param string    forumDesc The description of the forum.
     * @param string    forumIcon The icon URL or path for the forum (default is an empty string).
     * @param int       forumPosition The position of the forum (default is 1).
     * @param bool      isLocked Indicates whether the forum is locked (default is unlocked).
     *
     * @return void
     */
    static public function Create(Database $db, int $categoryID, string $forumName, string $forumDesc, string $forumIcon = '', int $forumPosition = 1, bool $isLocked = false)
    {
        $db->Query('INSERT INTO bit_forums (id,category_id,forum_name,forum_desc,forum_icon,forum_position,is_locked) VALUES (?,?,?,?,?,?,?)', [0, $categoryID, $forumName, $forumDesc, $forumIcon, $forumPosition, $isLocked ? 1 : 0]);
    }
}

?>