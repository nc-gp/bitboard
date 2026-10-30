<?php

namespace App\Forum\Controllers;

use RuntimeException;
use Throwable;

use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\SessionManager;

/**
 * Content editing, history, and permanent deletion helpers.
 */
class ContentController
{
    public static function CanEditThread(int $authorID): bool
    {
        return SessionManager::HasPermission(Permissions::EDITING_THREADS)
            && (self::IsAuthor($authorID) || SessionManager::IsStaff());
    }

    public static function CanDeleteThread(int $authorID): bool
    {
        return SessionManager::HasPermission(Permissions::DELETING_THREADS)
            && (self::IsAuthor($authorID) || SessionManager::IsStaff());
    }

    public static function CanEditPost(int $authorID): bool
    {
        return SessionManager::HasPermission(Permissions::EDITING_POSTS)
            && (self::IsAuthor($authorID) || SessionManager::IsStaff());
    }

    public static function CanDeletePost(int $authorID): bool
    {
        return SessionManager::HasPermission(Permissions::DELETING_POSTS)
            && (self::IsAuthor($authorID) || SessionManager::IsStaff());
    }

    private static function IsAuthor(int $authorID): bool
    {
        return SessionManager::IsLogged()
            && isset($_SESSION['bitboard_user'])
            && is_object($_SESSION['bitboard_user'])
            && (int) $_SESSION['bitboard_user']->id === $authorID;
    }

    public static function UpdateThread(Database $db, int $threadID, int $editorID, string $title, string $content, int $prefixID = 0): void
    {
        $db->BeginTransaction();

        try
        {
            $thread = $db->Query('SELECT thread_title, thread_content FROM bit_threads
                WHERE id = ? FOR UPDATE', $threadID)->FetchArray();

            if (empty($thread))
                throw new RuntimeException('The thread could not be loaded.');

            $db->Query('INSERT INTO bit_thread_edits (thread_id, user_id, thread_title, thread_content)
                VALUES (?, ?, ?, ?)', $threadID, $editorID, $thread['thread_title'], $thread['thread_content']);

            $db->Query('UPDATE bit_threads SET thread_title = ?, thread_content = ?, prefix_id = ? WHERE id = ?',
                $title, $content, $prefixID, $threadID);

            $db->Commit();
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }

    public static function UpdatePost(Database $db, int $postID, int $editorID, string $content): void
    {
        $db->BeginTransaction();

        try
        {
            $post = $db->Query('SELECT post_content FROM bit_posts
                WHERE id = ? FOR UPDATE', $postID)->FetchArray();

            if (empty($post))
                throw new RuntimeException('The post could not be loaded.');

            $db->Query('INSERT INTO bit_post_edits (post_id, user_id, post_content) VALUES (?, ?, ?)',
                $postID, $editorID, $post['post_content']);

            $db->Query('UPDATE bit_posts SET post_content = ? WHERE id = ?', $content, $postID);

            $db->Commit();
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }

    /**
     * Permanently delete a thread, every post inside it, and their related rows.
     */
    public static function DeleteThread(Database $db, int $threadID): void
    {
        $db->BeginTransaction();

        try
        {
            $postIDs = [];
            $rows = $db->Query('SELECT id FROM bit_posts WHERE thread_id = ?', $threadID)->FetchAll();
            foreach ($rows as $row)
                $postIDs[] = (int) $row['id'];

            if (count($postIDs) > 0)
            {
                $placeholders = implode(',', array_fill(0, count($postIDs), '?'));
                $db->Query('DELETE FROM bit_posts_likes WHERE post_id IN (' . $placeholders . ')', $postIDs);
                $db->Query('DELETE FROM bit_post_edits WHERE post_id IN (' . $placeholders . ')', $postIDs);
                $db->Query('DELETE FROM bit_posts WHERE thread_id = ?', $threadID);
            }

            $db->Query('DELETE FROM bit_threads_likes WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_thread_edits WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_notifications WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_thread_subscriptions WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_thread_reads WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_reports WHERE thread_id = ?', $threadID);
            $db->Query('DELETE FROM bit_threads WHERE id = ?', $threadID);

            $db->Commit();
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }

    /**
     * Permanently delete a post and its related rows.
     */
    public static function DeletePost(Database $db, int $postID): void
    {
        $db->BeginTransaction();

        try
        {
            $db->Query('DELETE FROM bit_posts_likes WHERE post_id = ?', $postID);
            $db->Query('DELETE FROM bit_post_edits WHERE post_id = ?', $postID);
            $db->Query('DELETE FROM bit_notifications WHERE post_id = ?', $postID);
            $db->Query('DELETE FROM bit_reports WHERE post_id = ?', $postID);
            $db->Query('DELETE FROM bit_posts WHERE id = ?', $postID);
            $db->Commit();
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }

    public static function GetThreadHistory(Database $db, int $threadID, int $limit = 20): array
    {
        return $db->Query('SELECT e.*, a.username, r.rank_format
            FROM bit_thread_edits AS e
            JOIN bit_accounts AS a ON a.id = e.user_id
            JOIN bit_ranks AS r ON r.id = a.rank_id
            WHERE e.thread_id = ?
            ORDER BY e.edited_at DESC, e.id DESC
            LIMIT ?', $threadID, $limit)->FetchAll();
    }

    public static function GetPostHistory(Database $db, int $postID, int $limit = 20): array
    {
        return $db->Query('SELECT e.*, a.username, r.rank_format
            FROM bit_post_edits AS e
            JOIN bit_accounts AS a ON a.id = e.user_id
            JOIN bit_ranks AS r ON r.id = a.rank_id
            WHERE e.post_id = ?
            ORDER BY e.edited_at DESC, e.id DESC
            LIMIT ?', $postID, $limit)->FetchAll();
    }

    public static function PostPageNumber(Database $db, array $post): int
    {
        $count = $db->Query('SELECT COUNT(*) AS post_position FROM bit_posts
            WHERE thread_id = ?
              AND (post_timestamp < ? OR (post_timestamp = ? AND id <= ?))',
            (int) $post['thread_id'],
            $post['post_timestamp'],
            $post['post_timestamp'],
            (int) $post['id']
        )->FetchArray();

        $position = (int) ($count['post_position'] ?? 1);

        return max(1, (int) ceil($position / PostController::POSTS_PER_PAGE));
    }
}

?>
