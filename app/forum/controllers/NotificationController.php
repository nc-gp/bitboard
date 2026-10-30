<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Persistent user notifications.
 */
class NotificationController
{
    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_notifications (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            actor_id INT(6) NOT NULL,
            type VARCHAR(32) NOT NULL,
            thread_id INT(6) NOT NULL DEFAULT 0,
            post_id INT(6) NOT NULL DEFAULT 0,
            message VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY notifications_user (user_id, is_read, created_at),
            KEY notifications_post (post_id)
        )');
    }

    public static function Create(
        Database $db,
        int $userID,
        int $actorID,
        string $type,
        int $threadID,
        int $postID,
        string $message,
        string $url
    ): void
    {
        $db->Query('INSERT INTO bit_notifications
            (id,user_id,actor_id,type,thread_id,post_id,message,url,is_read,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)',
            0,
            $userID,
            $actorID,
            $type,
            $threadID,
            $postID,
            $message,
            self::NormalizeUrl($url),
            0,
            date('Y-m-d H:i:s')
        );
    }

    /**
     * Notify a content author about a new reaction.
     */
    public static function NotifyReaction(
        Database $db,
        int $actorID,
        string $actorName,
        int $authorID,
        string $type,
        int $threadID,
        int $postID,
        string $threadTitle,
        string $url
    ): void
    {
        if ($actorID === $authorID || ($type !== 'thread_like' && $type !== 'post_like'))
            return;

        $message = $type === 'thread_like'
            ? $actorName . ' reacted to your thread “' . $threadTitle . '”'
            : $actorName . ' reacted to your post in “' . $threadTitle . '”';

        try
        {
            self::Create($db, $authorID, $actorID, $type, $threadID, $postID, $message, $url);
        }
        catch (Throwable $e)
        {
            // Reactions must keep working even when notifications fail.
            error_log('BitBoard reaction notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify a subscribed user about a new reply.
     */
    public static function NotifyThreadReply(
        Database $db,
        int $userID,
        int $actorID,
        string $actorName,
        int $threadID,
        int $postID,
        string $threadTitle,
        string $url
    ): void
    {
        if ($userID === $actorID)
            return;

        try
        {
            self::Create(
                $db,
                $userID,
                $actorID,
                'thread_reply',
                $threadID,
                $postID,
                $actorName . ' replied to “' . $threadTitle . '”',
                $url
            );
        }
        catch (Throwable $e)
        {
            error_log('BitBoard reply notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove a reaction notification when the reaction is withdrawn.
     */
    public static function DeleteReaction(Database $db, int $actorID, string $type, int $threadID, int $postID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_notifications
                WHERE actor_id = ? AND type = ? AND thread_id = ? AND post_id = ?',
                $actorID, $type, $threadID, $postID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard reaction notification cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Store internal notification targets as root-relative paths.
     */
    private static function NormalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['path']) || $parts['path'] === '')
            return $url;

        $normalized = $parts['path'];

        if (!str_starts_with($normalized, '/'))
            $normalized = '/' . $normalized;

        if (isset($parts['query']))
            $normalized .= '?' . $parts['query'];

        if (isset($parts['fragment']))
            $normalized .= '#' . $parts['fragment'];

        return $normalized;
    }

    public static function CountUnread(Database $db, int $userID): int
    {
        $count = $db->Query('SELECT COUNT(*) AS unread_count FROM bit_notifications
            WHERE user_id = ? AND is_read = 0', $userID)->FetchArray();

        return (int) ($count['unread_count'] ?? 0);
    }

    public static function GetForUser(Database $db, int $userID, int $offset, int $limit): array
    {
        return $db->Query('SELECT n.*
            FROM bit_notifications AS n
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT ?, ?', $userID, $offset, $limit)->FetchAll();
    }

    public static function GetForUserByID(Database $db, int $id, int $userID): array
    {
        return $db->Query('SELECT * FROM bit_notifications
            WHERE id = ? AND user_id = ? LIMIT 1', $id, $userID)->FetchArray();
    }

    public static function MarkRead(Database $db, int $id, int $userID): void
    {
        $db->Query('UPDATE bit_notifications SET is_read = 1
            WHERE id = ? AND user_id = ? AND is_read = 0', $id, $userID);
    }

    public static function MarkAllRead(Database $db, int $userID): void
    {
        $db->Query('UPDATE bit_notifications SET is_read = 1
            WHERE user_id = ? AND is_read = 0', $userID);
    }

    public static function DeleteForThread(Database $db, int $threadID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_notifications
                WHERE thread_id = ?
                    OR post_id IN (SELECT id FROM bit_posts WHERE thread_id = ?)', $threadID, $threadID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard notification cleanup failed: ' . $e->getMessage());
        }
    }

    public static function DeleteForPost(Database $db, int $postID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_notifications WHERE post_id = ?', $postID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard notification cleanup failed: ' . $e->getMessage());
        }
    }
}

?>
