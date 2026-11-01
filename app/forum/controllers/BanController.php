<?php

namespace App\Forum\Controllers;

use App\Classes\Database;
use DateTime;

/**
 * Stores ban and suspension records, including reasons and optional expiry.
 */
class BanController
{
    public const DURATIONS = [
        0 => 'Permanent',
        1 => '1 day',
        3 => '3 days',
        7 => '7 days',
        14 => '14 days',
        30 => '30 days',
        90 => '90 days',
    ];

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_user_bans (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) UNSIGNED NOT NULL,
            issued_by INT(6) UNSIGNED NULL DEFAULT NULL,
            reason VARCHAR(500) NOT NULL,
            issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            lifted_at TIMESTAMP NULL DEFAULT NULL,
            lifted_by INT(6) UNSIGNED NULL DEFAULT NULL,
            KEY bans_user_active (user_id, lifted_at, expires_at),
            KEY bans_issued (issued_at),
            CONSTRAINT fk_bans_user FOREIGN KEY (user_id) REFERENCES bit_accounts (id) ON DELETE CASCADE,
            CONSTRAINT fk_bans_issuer FOREIGN KEY (issued_by) REFERENCES bit_accounts (id) ON DELETE SET NULL,
            CONSTRAINT fk_bans_lifter FOREIGN KEY (lifted_by) REFERENCES bit_accounts (id) ON DELETE SET NULL
        )');
    }

    /**
     * Get the active ban for a user, or an empty array when the user is not banned.
     */
    public static function GetActiveForUser(Database $db, int $userID): array
    {
        return $db->Query('SELECT b.*, i.username AS issuer_name
            FROM bit_user_bans AS b
            LEFT JOIN bit_accounts AS i ON i.id = b.issued_by
            WHERE b.user_id = ?
                AND b.lifted_at IS NULL
                AND (b.expires_at IS NULL OR b.expires_at > NOW())
            ORDER BY b.id DESC
            LIMIT 1', $userID)->FetchArray();
    }

    public static function HasActive(Database $db, int $userID): bool
    {
        return !empty(self::GetActiveForUser($db, $userID));
    }

    public static function GetByID(Database $db, int $banID): array
    {
        return $db->Query('SELECT b.*, u.username AS user_name, i.username AS issuer_name, l.username AS lifter_name
            FROM bit_user_bans AS b
            JOIN bit_accounts AS u ON u.id = b.user_id
            LEFT JOIN bit_accounts AS i ON i.id = b.issued_by
            LEFT JOIN bit_accounts AS l ON l.id = b.lifted_by
            WHERE b.id = ?
            LIMIT 1', $banID)->FetchArray();
    }

    public static function Create(
        Database $db,
        int $userID,
        int $issuedBy,
        string $reason,
        int $durationDays
    ): int
    {
        if ($durationDays > 0)
        {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days'));

            $db->Query('INSERT INTO bit_user_bans
                (id, user_id, issued_by, reason, expires_at)
                VALUES (?,?,?,?,?)',
                [0, $userID, $issuedBy, $reason, $expiresAt]
            );
        }
        else
        {
            $db->Query('INSERT INTO bit_user_bans
                (id, user_id, issued_by, reason)
                VALUES (?,?,?,?)',
                [0, $userID, $issuedBy, $reason]
            );
        }

        return (int) $db->LastInsertID();
    }

    public static function Lift(Database $db, int $banID, int $liftedBy): void
    {
        $db->Query('UPDATE bit_user_bans
            SET lifted_at = NOW(), lifted_by = ?
            WHERE id = ?
                AND lifted_at IS NULL
                AND (expires_at IS NULL OR expires_at > NOW())',
            $liftedBy,
            $banID
        );
    }

    public static function LiftActiveForUser(Database $db, int $userID, int $liftedBy): bool
    {
        $ban = self::GetActiveForUser($db, $userID);

        if (empty($ban))
            return false;

        self::Lift($db, (int) $ban['id'], $liftedBy);

        return true;
    }

    public static function Count(Database $db, string $status): int
    {
        $where = self::StatusWhere($status);
        $row = $db->Query('SELECT COUNT(*) AS total FROM bit_user_bans AS b WHERE ' . $where)->FetchArray();

        return (int) ($row['total'] ?? 0);
    }

    public static function GetPage(Database $db, string $status, int $offset, int $limit): array
    {
        $where = self::StatusWhere($status);

        return $db->Query('SELECT b.*,
                u.username AS user_name,
                r.rank_position AS user_rank_position,
                i.username AS issuer_name,
                l.username AS lifter_name
            FROM bit_user_bans AS b
            JOIN bit_accounts AS u ON u.id = b.user_id
            JOIN bit_ranks AS r ON r.id = u.rank_id
            LEFT JOIN bit_accounts AS i ON i.id = b.issued_by
            LEFT JOIN bit_accounts AS l ON l.id = b.lifted_by
            WHERE ' . $where . '
            ORDER BY b.id DESC
            LIMIT ?, ?', $offset, $limit)->FetchAll();
    }

    public static function StatusWhere(string $status): string
    {
        return match ($status)
        {
            'active' => 'b.lifted_at IS NULL AND (b.expires_at IS NULL OR b.expires_at > NOW())',
            'expired' => 'b.lifted_at IS NULL AND b.expires_at IS NOT NULL AND b.expires_at <= NOW()',
            'lifted' => 'b.lifted_at IS NOT NULL',
            default => '1 = 1',
        };
    }

    public static function Status(array $ban): string
    {
        if (!empty($ban['lifted_at']))
            return 'lifted';

        if (!empty($ban['expires_at']))
        {
            $expires = new DateTime($ban['expires_at']);

            if ($expires->getTimestamp() <= time())
                return 'expired';
        }

        return 'active';
    }

    public static function DurationLabel(int $durationDays): string
    {
        return self::DURATIONS[$durationDays] ?? 'Permanent';
    }

    public static function ExpiryLabel(?string $expiresAt): string
    {
        if ($expiresAt === null || trim($expiresAt) === '')
            return 'Permanent';

        $timestamp = strtotime($expiresAt);

        return $timestamp === false ? $expiresAt : date('M j, Y \a\t g:i A', $timestamp);
    }
}
