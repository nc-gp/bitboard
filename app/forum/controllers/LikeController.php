<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;
use App\Classes\Reactions;

class LikeController
{
    private static bool $schemaChecked = false;

    /**
     * Add the reaction column and one-like-per-user indexes to older installs.
     */
    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $column = $db->Query("SELECT COUNT(*) AS column_count FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'bit_posts_likes' AND column_name = 'reaction'")->FetchArray();

            if ((int) ($column['column_count'] ?? 0) === 0)
                $db->Query("ALTER TABLE bit_posts_likes ADD COLUMN reaction VARCHAR(16) NOT NULL DEFAULT 'heart' AFTER post_id");

            $threadColumn = $db->Query("SELECT COUNT(*) AS column_count FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'bit_threads_likes' AND column_name = 'reaction'")->FetchArray();

            if ((int) ($threadColumn['column_count'] ?? 0) === 0)
                $db->Query("ALTER TABLE bit_threads_likes ADD COLUMN reaction VARCHAR(16) NOT NULL DEFAULT 'heart' AFTER thread_id");

            $indexes = $db->Query("SELECT table_name, index_name FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND index_name IN ('uniq_thread_like', 'uniq_post_like')")->FetchAll();

            $existing = [];
            foreach ($indexes as $index)
                $existing[$index['table_name'] . '.' . $index['index_name']] = true;

            if (!isset($existing['bit_threads_likes.uniq_thread_like']))
            {
                $db->Query('DELETE l1 FROM bit_threads_likes AS l1
                    JOIN bit_threads_likes AS l2
                        ON l1.user_id = l2.user_id AND l1.thread_id = l2.thread_id AND l1.id > l2.id');
                $db->Query('ALTER TABLE bit_threads_likes ADD UNIQUE INDEX uniq_thread_like (user_id, thread_id)');
            }

            if (!isset($existing['bit_posts_likes.uniq_post_like']))
            {
                $db->Query('DELETE l1 FROM bit_posts_likes AS l1
                    JOIN bit_posts_likes AS l2
                        ON l1.user_id = l2.user_id AND l1.post_id = l2.post_id AND l1.id > l2.id');
                $db->Query('ALTER TABLE bit_posts_likes ADD UNIQUE INDEX uniq_post_like (user_id, post_id)');
            }
        }
        catch (Throwable $e)
        {
            error_log('BitBoard like schema check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get per-reaction counts and the current user's reactions for several posts.
     *
     * @return array [counts by post ID, current user's reaction by post ID]
     */
    public static function GetPostReactions(Database $db, array $postIDs, ?int $userId): array
    {
        $postIDs = array_values(array_unique(array_map('intval', $postIDs)));

        if (count($postIDs) <= 0)
            return [[], []];

        $placeholders = implode(',', array_fill(0, count($postIDs), '?'));
        $rows = $db->Query('SELECT post_id, user_id, reaction FROM bit_posts_likes
            WHERE post_id IN (' . $placeholders . ')', $postIDs)->FetchAll();

        $counts = [];
        foreach ($postIDs as $postID)
            $counts[$postID] = array_fill_keys(array_keys(Reactions::ALL), 0);

        $userReactions = [];

        foreach ($rows as $row)
        {
            $postID = (int) $row['post_id'];
            $reaction = Reactions::Normalize($row['reaction']);

            if (isset($counts[$postID]))
                $counts[$postID][$reaction]++;

            if ($userId !== null && (int) $row['user_id'] === $userId)
                $userReactions[$postID] = $reaction;
        }

        return [$counts, $userReactions];
    }

    /**
     * Get per-reaction counts and the current user's reaction for a thread.
     *
     * @return array [counts, current user's reaction, total count]
     */
    public static function GetThreadReactions(Database $db, int $threadID, ?int $userId): array
    {
        $rows = $db->Query('SELECT user_id, reaction FROM bit_threads_likes WHERE thread_id = ?', $threadID)->FetchAll();
        $counts = array_fill_keys(array_keys(Reactions::ALL), 0);
        $userReaction = null;
        $total = 0;

        foreach ($rows as $row)
        {
            $reaction = Reactions::Normalize($row['reaction']);
            $counts[$reaction]++;
            $total++;

            if ($userId !== null && (int) $row['user_id'] === $userId)
                $userReaction = $reaction;
        }

        return [$counts, $userReaction, $total];
    }

    /**
     * Add, change, or remove the current user's reaction to a thread.
     */
    public static function ToggleThreadReaction(Database $db, int $userId, int $threadID, string $reaction): array
    {
        if (!Reactions::IsValid($reaction))
            throw new \InvalidArgumentException('Unsupported reaction.');

        $db->BeginTransaction();

        try
        {
            $existing = $db->Query('SELECT id, reaction FROM bit_threads_likes
                WHERE user_id = ? AND thread_id = ? FOR UPDATE', $userId, $threadID)->FetchArray();

            if (!empty($existing))
            {
                if (Reactions::Normalize($existing['reaction']) === $reaction)
                {
                    $db->Query('DELETE FROM bit_threads_likes WHERE id = ?', $existing['id']);
                    $liked = false;
                }
                else
                {
                    $db->Query('UPDATE bit_threads_likes SET reaction = ? WHERE id = ?', $reaction, $existing['id']);
                    $liked = true;
                }
            }
            else
            {
                $db->Query('INSERT INTO bit_threads_likes (user_id, thread_id, reaction) VALUES (?, ?, ?)', $userId, $threadID, $reaction);
                $liked = true;
            }

            $rows = $db->Query('SELECT reaction FROM bit_threads_likes WHERE thread_id = ?', $threadID)->FetchAll();
            $counts = array_fill_keys(array_keys(Reactions::ALL), 0);
            $total = 0;

            foreach ($rows as $row)
            {
                $counts[Reactions::Normalize($row['reaction'])]++;
                $total++;
            }

            $db->Query('UPDATE bit_threads SET thread_likes = ? WHERE id = ?', $total, $threadID);
            $db->Commit();

            return [
                'liked' => $liked,
                'reaction' => $liked ? $reaction : null,
                'count' => $total,
                'counts' => $counts,
            ];
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }

    /**
     * Add, change, or remove the current user's reaction to a post.
     */
    public static function TogglePostReaction(Database $db, int $userId, int $postID, string $reaction): array
    {
        if (!Reactions::IsValid($reaction))
            throw new \InvalidArgumentException('Unsupported reaction.');

        $db->BeginTransaction();

        try
        {
            $existing = $db->Query('SELECT id, reaction FROM bit_posts_likes
                WHERE user_id = ? AND post_id = ? FOR UPDATE', $userId, $postID)->FetchArray();

            if (!empty($existing))
            {
                if (Reactions::Normalize($existing['reaction']) === $reaction)
                {
                    $db->Query('DELETE FROM bit_posts_likes WHERE id = ?', $existing['id']);
                    $liked = false;
                }
                else
                {
                    $db->Query('UPDATE bit_posts_likes SET reaction = ? WHERE id = ?', $reaction, $existing['id']);
                    $liked = true;
                }
            }
            else
            {
                $db->Query('INSERT INTO bit_posts_likes (user_id, post_id, reaction) VALUES (?, ?, ?)', $userId, $postID, $reaction);
                $liked = true;
            }

            $rows = $db->Query('SELECT reaction FROM bit_posts_likes WHERE post_id = ?', $postID)->FetchAll();
            $counts = array_fill_keys(array_keys(Reactions::ALL), 0);
            $total = 0;

            foreach ($rows as $row)
            {
                $counts[Reactions::Normalize($row['reaction'])]++;
                $total++;
            }

            $db->Query('UPDATE bit_posts SET post_likes = ? WHERE id = ?', $total, $postID);
            $db->Commit();

            return [
                'liked' => $liked,
                'reaction' => $liked ? $reaction : null,
                'count' => $total,
                'counts' => $counts,
            ];
        }
        catch (Throwable $e)
        {
            $db->RollBack();
            throw $e;
        }
    }
}

?>
