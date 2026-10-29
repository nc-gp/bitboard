<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\SessionManager;

/**
 * Persistent audit trail for administrative and moderation actions.
 */
class AuditController
{
    public const LABELS = [
        'settings.update' => 'Settings updated',
        'avatars.update' => 'Avatar settings updated',
        'category.create' => 'Category created',
        'category.update' => 'Category updated',
        'category.delete' => 'Category deleted',
        'forum.create' => 'Forum created',
        'forum.update' => 'Forum updated',
        'forum.delete' => 'Forum deleted',
        'subforum.create' => 'Subforum created',
        'subforum.update' => 'Subforum updated',
        'subforum.delete' => 'Subforum deleted',
        'prefix.create' => 'Prefix created',
        'prefix.update' => 'Prefix updated',
        'prefix.delete' => 'Prefix deleted',
        'rank.create' => 'Rank created',
        'rank.update' => 'Rank updated',
        'rank.delete' => 'Rank deleted',
        'user.update' => 'User updated',
        'user.ban' => 'User banned',
        'user.unban' => 'User unbanned',
        'thread.pin' => 'Thread pinned',
        'thread.unpin' => 'Thread unpinned',
        'thread.lock' => 'Thread locked',
        'thread.unlock' => 'Thread unlocked',
        'thread.move' => 'Thread moved',
        'thread.edit' => 'Thread edited',
        'thread.delete' => 'Thread deleted',
        'post.edit' => 'Post edited',
        'post.delete' => 'Post deleted',
        'report.resolved' => 'Report resolved',
        'report.dismissed' => 'Report dismissed',
    ];

    private static bool $schemaChecked = false;

    public static function EnsureSchema(Database $db): void
    {
        if (self::$schemaChecked)
            return;

        self::$schemaChecked = true;

        try
        {
            $state = $db->Query("SELECT COUNT(*) AS table_count FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'bit_audit_log'")->FetchArray();

            if ((int) ($state['table_count'] ?? 0) === 1)
                return;

            self::CreateTable($db);

            // Existing installations need the new permission on the built-in
            // Admin rank. Fresh installs already receive it through Permissions::$All.
            $admin = $db->Query('SELECT rank_flags FROM bit_ranks WHERE id = 1')->FetchArray();

            if (!empty($admin) && (((int) $admin['rank_flags'] & Permissions::VIEWING_AUDIT_LOG) === 0))
            {
                $db->Query(
                    'UPDATE bit_ranks SET rank_flags = rank_flags | ? WHERE id = 1',
                    Permissions::VIEWING_AUDIT_LOG
                );
            }
        }
        catch (Throwable $e)
        {
            error_log('BitBoard audit schema check failed: ' . $e->getMessage());
        }
    }

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_audit_log (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id INT(6) NOT NULL DEFAULT 0,
            action VARCHAR(64) NOT NULL,
            target_type VARCHAR(32) NOT NULL DEFAULT \'\',
            target_id INT(6) NOT NULL DEFAULT 0,
            details VARCHAR(1000) NOT NULL DEFAULT \'\',
            ip_address VARCHAR(64) NOT NULL DEFAULT \'\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY audit_created (created_at),
            KEY audit_actor (actor_id),
            KEY audit_action (action)
        )');
    }

    public static function Log(
        Database $db,
        string $action,
        string $details,
        string $targetType = '',
        int $targetID = 0
    ): void
    {
        try
        {
            $actorID = 0;

            if (SessionManager::IsLogged()
                && isset($_SESSION['bitboard_user'])
                && is_object($_SESSION['bitboard_user'])
                && isset($_SESSION['bitboard_user']->id))
                $actorID = (int) $_SESSION['bitboard_user']->id;

            $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                ? mb_substr($_SERVER['REMOTE_ADDR'], 0, 64)
                : '';

            $db->Query('INSERT INTO bit_audit_log
                (id, actor_id, action, target_type, target_id, details, ip_address)
                VALUES (?,?,?,?,?,?,?)',
                [
                    0,
                    $actorID,
                    mb_substr($action, 0, 64),
                    mb_substr($targetType, 0, 32),
                    $targetID,
                    mb_substr($details, 0, 1000),
                    $ip,
                ]
            );
        }
        catch (Throwable $e)
        {
            // Auditing must never break the action it is recording.
            error_log('BitBoard audit log failed: ' . $e->getMessage());
        }
    }

    private static function BuildWhere(string $action, string $actorSearch, array &$parameters): string
    {
        $where = '1 = 1';

        if ($action !== '')
        {
            $where .= ' AND l.action = ?';
            $parameters[] = $action;
        }

        if ($actorSearch !== '')
        {
            $where .= ' AND a.username LIKE ?';
            $parameters[] = '%' . $actorSearch . '%';
        }

        return $where;
    }

    public static function Count(Database $db, string $action, string $actorSearch): int
    {
        $parameters = [];
        $where = self::BuildWhere($action, $actorSearch, $parameters);
        $sql = 'SELECT COUNT(*) AS total
            FROM bit_audit_log AS l
            LEFT JOIN bit_accounts AS a ON a.id = l.actor_id
            WHERE ' . $where;

        $row = count($parameters) > 0
            ? $db->Query($sql, $parameters)->FetchArray()
            : $db->Query($sql)->FetchArray();

        return (int) ($row['total'] ?? 0);
    }

    public static function GetPage(
        Database $db,
        string $action,
        string $actorSearch,
        int $offset,
        int $limit
    ): array
    {
        $parameters = [];
        $where = self::BuildWhere($action, $actorSearch, $parameters);
        $parameters[] = $offset;
        $parameters[] = $limit;

        return $db->Query('SELECT l.*, a.username AS actor_name
            FROM bit_audit_log AS l
            LEFT JOIN bit_accounts AS a ON a.id = l.actor_id
            WHERE ' . $where . '
            ORDER BY l.id DESC
            LIMIT ?, ?', $parameters)->FetchAll();
    }

    public static function GetActions(Database $db): array
    {
        return $db->Query('SELECT DISTINCT action FROM bit_audit_log
            ORDER BY action ASC')->FetchAll();
    }

    public static function Label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
