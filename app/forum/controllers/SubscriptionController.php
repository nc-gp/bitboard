<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Thread subscription storage and reply notifications.
 */
class SubscriptionController
{
    private static bool $schemaChecked = false;

    /**
     * Create the subscriptions table for older installations.
     */
    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $state = $db->Query("SELECT COUNT(*) AS table_count FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'bit_thread_subscriptions'")->FetchArray();

            if ((int) ($state['table_count'] ?? 0) === 1)
                return;

            self::CreateTable($db);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard subscription schema check failed: ' . $e->getMessage());
        }
    }

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_thread_subscriptions (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_thread_subscription (user_id, thread_id),
            KEY thread_subscriptions_thread (thread_id)
        )');
    }

    public static function IsSubscribed(Database $db, int $userID, int $threadID): bool
    {
        $row = $db->Query('SELECT id FROM bit_thread_subscriptions
            WHERE user_id = ? AND thread_id = ? LIMIT 1', $userID, $threadID)->FetchArray();

        return !empty($row);
    }

    public static function Subscribe(Database $db, int $userID, int $threadID): void
    {
        $db->Query('INSERT INTO bit_thread_subscriptions (user_id, thread_id, created_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE created_at = created_at',
            $userID, $threadID, date('Y-m-d H:i:s'));
    }

    public static function Unsubscribe(Database $db, int $userID, int $threadID): void
    {
        $db->Query('DELETE FROM bit_thread_subscriptions
            WHERE user_id = ? AND thread_id = ?', $userID, $threadID);
    }

    /**
     * Notify every subscriber of a new reply except the author.
     */
    public static function NotifySubscribers(
        Database $db,
        int $threadID,
        int $postID,
        int $actorID,
        string $actorName,
        string $threadTitle,
        string $url
    ): void
    {
        try
        {
            $rows = $db->Query('SELECT user_id FROM bit_thread_subscriptions
                WHERE thread_id = ? AND user_id <> ?', $threadID, $actorID)->FetchAll();

            foreach ($rows as $row)
            {
                NotificationController::NotifyThreadReply(
                    $db,
                    (int) $row['user_id'],
                    $actorID,
                    $actorName,
                    $threadID,
                    $postID,
                    $threadTitle,
                    $url
                );
            }
        }
        catch (Throwable $e)
        {
            // Replies must keep working even when notifications fail.
            error_log('BitBoard subscription notification failed: ' . $e->getMessage());
        }
    }

    public static function DeleteForThread(Database $db, int $threadID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_thread_subscriptions WHERE thread_id = ?', $threadID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard subscription cleanup failed: ' . $e->getMessage());
        }
    }
}

?>
