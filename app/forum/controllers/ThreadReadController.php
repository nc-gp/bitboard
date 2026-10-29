<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Per-user thread read positions for jump-to-unread links.
 */
class ThreadReadController
{
    private static bool $schemaChecked = false;

    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $state = $db->Query("SELECT COUNT(*) AS table_count FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'bit_thread_reads'")->FetchArray();

            if ((int) ($state['table_count'] ?? 0) === 1)
                return;

            self::CreateTable($db);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard thread read schema check failed: ' . $e->getMessage());
        }
    }

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_thread_reads (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            last_read_post_id INT(6) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_thread_read (user_id, thread_id),
            KEY thread_reads_thread (thread_id)
        )');
    }

    public static function MarkRead(Database $db, int $userID, int $threadID, int $postID): void
    {
        if ($userID <= 0 || $threadID <= 0 || $postID <= 0)
            return;

        $now = date('Y-m-d H:i:s');

        $db->Query('INSERT INTO bit_thread_reads
            (user_id, thread_id, last_read_post_id, updated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                last_read_post_id = GREATEST(last_read_post_id, ?),
                updated_at = ?',
            $userID, $threadID, $postID, $now, $postID, $now);
    }

    /**
     * @return array<int, array{read_count: int, first_unread_id: int}>
     */
    public static function GetReadData(Database $db, int $userID, array $threadIDs): array
    {
        $threadIDs = array_values(array_unique(array_map('intval', $threadIDs)));

        if ($userID <= 0 || count($threadIDs) === 0)
            return [];

        $placeholders = implode(',', array_fill(0, count($threadIDs), '?'));
        $parameters = array_merge([$userID], $threadIDs);

        $rows = $db->Query('SELECT r.thread_id, r.last_read_post_id,
                (SELECT COUNT(*) FROM bit_posts AS p
                    WHERE p.thread_id = r.thread_id AND p.id <= r.last_read_post_id) AS read_count,
                (SELECT MIN(p.id) FROM bit_posts AS p
                    WHERE p.thread_id = r.thread_id AND p.id > r.last_read_post_id) AS first_unread_id
            FROM bit_thread_reads AS r
            WHERE r.user_id = ? AND r.thread_id IN (' . $placeholders . ')',
            $parameters)->FetchAll();

        $result = [];

        foreach ($rows as $row)
        {
            $result[(int) $row['thread_id']] = [
                'read_count' => (int) $row['read_count'],
                'first_unread_id' => (int) ($row['first_unread_id'] ?? 0),
            ];
        }

        return $result;
    }

    public static function DeleteForThread(Database $db, int $threadID): void
    {
        $db->Query('DELETE FROM bit_thread_reads WHERE thread_id = ?', $threadID);
    }
}
