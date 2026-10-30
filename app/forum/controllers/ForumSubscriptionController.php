<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Forum/subforum watch subscriptions and new-thread notifications.
 */
class ForumSubscriptionController
{

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_forum_subscriptions (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            forum_id INT(6) NOT NULL,
            subforum_id INT(6) NOT NULL DEFAULT -1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_forum_subscription (user_id, forum_id, subforum_id),
            KEY forum_subscriptions_target (forum_id, subforum_id)
        )');
    }

    public static function IsWatching(Database $db, int $userID, int $forumID, int $subforumID): bool
    {
        $row = $db->Query('SELECT id FROM bit_forum_subscriptions
            WHERE user_id = ? AND forum_id = ? AND subforum_id = ? LIMIT 1',
            $userID, $forumID, $subforumID)->FetchArray();

        return !empty($row);
    }

    public static function Watch(Database $db, int $userID, int $forumID, int $subforumID): void
    {
        $db->Query('INSERT INTO bit_forum_subscriptions
            (user_id, forum_id, subforum_id, created_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE created_at = created_at',
            $userID, $forumID, $subforumID, date('Y-m-d H:i:s'));
    }

    public static function Unwatch(Database $db, int $userID, int $forumID, int $subforumID): void
    {
        $db->Query('DELETE FROM bit_forum_subscriptions
            WHERE user_id = ? AND forum_id = ? AND subforum_id = ?',
            $userID, $forumID, $subforumID);
    }

    /**
     * Notify watchers of a forum and/or its subforum about a new thread.
     * A user watching both targets receives only one notification.
     */
    public static function NotifyNewThread(
        Database $db,
        int $forumID,
        int $subforumID,
        int $threadID,
        string $threadTitle,
        int $actorID,
        string $actorName,
        string $forumName,
        string $subforumName,
        string $url
    ): void
    {
        try
        {
            $rows = $db->Query('SELECT DISTINCT user_id
                FROM bit_forum_subscriptions
                WHERE user_id <> ?
                  AND forum_id = ?
                  AND (subforum_id = -1 OR subforum_id = ?)',
                $actorID, $forumID, $subforumID)->FetchAll();

            $location = $forumName;

            if ($subforumName !== '')
                $location .= ' / ' . $subforumName;

            $message = $actorName . ' started “' . $threadTitle . '” in ' . $location;

            foreach ($rows as $row)
            {
                NotificationController::Create(
                    $db,
                    (int) $row['user_id'],
                    $actorID,
                    'forum_thread',
                    $threadID,
                    0,
                    $message,
                    $url
                );
            }
        }
        catch (Throwable $e)
        {
            // New threads must keep working even when notifications fail.
            error_log('BitBoard forum subscription notification failed: ' . $e->getMessage());
        }
    }

    public static function DeleteForSubforum(Database $db, int $subforumID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_forum_subscriptions WHERE subforum_id = ?', $subforumID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard forum subscription cleanup failed: ' . $e->getMessage());
        }
    }
}
