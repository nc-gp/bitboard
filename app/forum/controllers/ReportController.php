<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * User reports and the staff report queue.
 */
class ReportController
{
    public const REASONS = [
        'spam' => 'Spam or advertising',
        'harassment' => 'Harassment or abuse',
        'offtopic' => 'Off-topic',
        'inappropriate' => 'Inappropriate content',
        'other' => 'Other',
    ];

    public const STATUSES = ['open', 'resolved', 'dismissed'];

    private static bool $schemaChecked = false;

    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $state = $db->Query("SELECT COUNT(*) AS table_count FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'bit_reports'")->FetchArray();

            if ((int) ($state['table_count'] ?? 0) === 1)
                return;

            self::CreateTable($db);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard report schema check failed: ' . $e->getMessage());
        }
    }

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_reports (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT(6) NOT NULL,
            thread_id INT(6) NOT NULL,
            post_id INT(6) NOT NULL DEFAULT 0,
            reason VARCHAR(32) NOT NULL,
            details VARCHAR(1000) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'open\',
            note VARCHAR(1000) NOT NULL DEFAULT \'\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_by INT(6) NULL DEFAULT NULL,
            resolved_at TIMESTAMP NULL DEFAULT NULL,
            KEY reports_status (status, created_at),
            KEY reports_thread (thread_id),
            KEY reports_post (post_id)
        )');
    }

    public static function Create(
        Database $db,
        int $reporterID,
        int $threadID,
        int $postID,
        string $reason,
        string $details
    ): int
    {
        $db->Query('INSERT INTO bit_reports
            (reporter_id, thread_id, post_id, reason, details, status, note, created_at)
            VALUES (?,?,?,?,?,?,?,?)',
            $reporterID,
            $threadID,
            $postID,
            $reason,
            $details,
            'open',
            '',
            date('Y-m-d H:i:s')
        );

        return (int) $db->LastInsertID();
    }

    public static function HasOpenReport(Database $db, int $reporterID, int $threadID, int $postID): bool
    {
        $row = $db->Query('SELECT id FROM bit_reports
            WHERE reporter_id = ? AND thread_id = ? AND post_id = ? AND status = \'open\'
            LIMIT 1', $reporterID, $threadID, $postID)->FetchArray();

        return !empty($row);
    }

    public static function CountOpen(Database $db): int
    {
        $row = $db->Query('SELECT COUNT(*) AS report_count FROM bit_reports WHERE status = \'open\'')->FetchArray();

        return (int) ($row['report_count'] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    public static function CountsByStatus(Database $db): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        $counts['all'] = 0;
        $rows = $db->Query('SELECT status, COUNT(*) AS report_count FROM bit_reports GROUP BY status')->FetchAll();

        foreach ($rows as $row)
        {
            $status = (string) $row['status'];
            $count = (int) $row['report_count'];
            $counts['all'] += $count;

            if (isset($counts[$status]))
                $counts[$status] = $count;
        }

        return $counts;
    }

    public static function CountReports(Database $db, string $status): int
    {
        if ($status === 'all')
        {
            $row = $db->Query('SELECT COUNT(*) AS report_count FROM bit_reports')->FetchArray();
            return (int) ($row['report_count'] ?? 0);
        }

        $row = $db->Query('SELECT COUNT(*) AS report_count FROM bit_reports
            WHERE status = ?', $status)->FetchArray();

        return (int) ($row['report_count'] ?? 0);
    }

    public static function GetReports(Database $db, string $status, int $offset, int $limit): array
    {
        $where = $status === 'all' ? '' : 'WHERE r.status = ?';
        $parameters = $status === 'all' ? [] : [$status];
        $parameters[] = $offset;
        $parameters[] = $limit;

        return $db->Query('SELECT r.*, a.username AS reporter_name, ar.rank_format AS reporter_rank,
                t.thread_title, t.forum_id, t.subforum_id,
                p.post_content, p.post_timestamp, pa.username AS post_author,
                rb.username AS resolver_name
            FROM bit_reports AS r
            JOIN bit_threads AS t ON t.id = r.thread_id
            JOIN bit_accounts AS a ON a.id = r.reporter_id
            JOIN bit_ranks AS ar ON ar.id = a.rank_id
            LEFT JOIN bit_posts AS p ON p.id = r.post_id AND r.post_id > 0
            LEFT JOIN bit_accounts AS pa ON pa.id = p.user_id
            LEFT JOIN bit_accounts AS rb ON rb.id = r.resolved_by
            ' . $where . '
            ORDER BY r.created_at ASC, r.id ASC
            LIMIT ?, ?', $parameters)->FetchAll();
    }

    public static function GetByID(Database $db, int $reportID): array
    {
        return $db->Query('SELECT * FROM bit_reports WHERE id = ? LIMIT 1', $reportID)->FetchArray();
    }

    public static function Resolve(Database $db, int $reportID, int $userID, string $status, string $note): void
    {
        if (!in_array($status, ['resolved', 'dismissed'], true))
            throw new \InvalidArgumentException('Unsupported report status.');

        $db->Query('UPDATE bit_reports SET status = ?, note = ?, resolved_by = ?, resolved_at = ?
            WHERE id = ? AND status = \'open\'',
            $status,
            $note,
            $userID,
            date('Y-m-d H:i:s'),
            $reportID
        );
    }

    public static function DeleteForThread(Database $db, int $threadID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_reports WHERE thread_id = ?', $threadID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard report cleanup failed: ' . $e->getMessage());
        }
    }

    public static function DeleteForPost(Database $db, int $postID): void
    {
        try
        {
            $db->Query('DELETE FROM bit_reports WHERE post_id = ?', $postID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard report cleanup failed: ' . $e->getMessage());
        }
    }
}

?>
