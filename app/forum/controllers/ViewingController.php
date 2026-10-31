<?php

namespace App\Forum\Controllers;

use Throwable;

use App\Classes\Database;

/**
 * Tracks which forum, subforum, or thread a member is currently viewing.
 */
class ViewingController
{
    /**
     * Store the current location for a member.
     *
     * Only writes when the location changes, so browsing does not add a
     * database write to every request.
     */
    public static function Track(Database $db, int $userID, string $type, int $id): void
    {
        if ($userID <= 0)
            return;

        if (!in_array($type, ['forum', 'subforum', 'thread'], true))
        {
            $type = '';
            $id = 0;
        }

        if ($id < 0)
            $id = 0;

        $key = $type . ':' . $id;

        if (($_SESSION['bb_viewing'] ?? null) === $key)
            return;

        try
        {
            $db->Query(
                'UPDATE bit_accounts SET viewing_type = ?, viewing_id = ? WHERE id = ?',
                $type,
                $id,
                $userID
            );

            $_SESSION['bb_viewing'] = $key;
        }
        catch (Throwable $e)
        {
            // Presence is non-critical; a failure must not take the page down.
            error_log('BitBoard: unable to update viewing location for account ' . $userID . ': ' . $e->getMessage());
        }
    }

    /**
     * Clear the stored location, for example on logout.
     */
    public static function Clear(Database $db, int $userID): void
    {
        if ($userID <= 0)
            return;

        try
        {
            $db->Query('UPDATE bit_accounts SET viewing_type = \'\', viewing_id = 0 WHERE id = ?', $userID);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard: unable to clear viewing location for account ' . $userID . ': ' . $e->getMessage());
        }
    }

    /**
     * Count members currently viewing a target.
     */
    public static function CountViewing(Database $db, string $type, int $id): int
    {
        if ($id <= 0 || !in_array($type, ['forum', 'subforum', 'thread'], true))
            return 0;

        $row = $db->Query('SELECT COUNT(*) AS viewing_count FROM bit_accounts
            WHERE is_system = 0 AND viewing_type = ? AND viewing_id = ? AND last_active >= ?',
            $type,
            $id,
            AccountController::OnlineCutoff()
        )->FetchArray();

        return (int) ($row['viewing_count'] ?? 0);
    }

    /**
     * Get members currently viewing a target.
     *
     * @return array The viewing account rows.
     */
    public static function GetViewing(Database $db, string $type, int $id, int $limit): array
    {
        if ($id <= 0 || $limit <= 0 || !in_array($type, ['forum', 'subforum', 'thread'], true))
            return [];

        return $db->Query('SELECT a.id, a.username, a.avatar, r.rank_name, r.rank_format
            FROM bit_accounts AS a
            JOIN bit_ranks AS r ON r.id = a.rank_id
            WHERE a.is_system = 0 AND a.viewing_type = ? AND a.viewing_id = ? AND a.last_active >= ?
            ORDER BY a.last_active DESC, a.username ASC
            LIMIT ?',
            $type,
            $id,
            AccountController::OnlineCutoff(),
            $limit
        )->FetchAll();
    }
}
