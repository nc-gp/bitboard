<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\UrlManager;

use App\Forum\Controllers\NotificationController;

use App\Interfaces\PageInterface;

/**
 * JSON endpoint for the header notification panel.
 *
 * There is no standalone notifications page: normal browser requests are
 * redirected home, while the panel fetches JSON with an AJAX request.
 */
class NotificationsPage extends PageBase implements PageInterface
{
    private const PANEL_LIMIT = 10;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Notifications';
        $this->UrlHandler();
    }

    private function IsAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || (isset($_GET['ajax']) && $_GET['ajax'] === '1');
    }

    private function Json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    }

    private function UrlHandler(): void
    {
        $parameters = $this->forumData->actionParameters;
        $action = isset($parameters[1]) && is_string($parameters[1]) ? $parameters[1] : '';
        $isAjax = $this->IsAjax();

        if (!SessionManager::IsLogged())
        {
            if ($isAjax)
                $this->Json(['error' => 'login_required'], 401);

            UrlManager::Redirect($this->serverPath . 'login');
        }

        if ($action === 'open')
        {
            $id = isset($parameters[2]) && is_string($parameters[2]) && ctype_digit($parameters[2])
                ? (int) $parameters[2]
                : 0;

            if ($id <= 0 || count($parameters) !== 3)
                throw new PageNotFoundException();

            $this->Open($id);
        }

        if (count($parameters) > 1)
            throw new PageNotFoundException();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
            $this->HandleSubmission();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
            throw new PageNotFoundException();

        // Keep /notifications as an internal JSON endpoint only.
        if (!$isAjax)
            UrlManager::Redirect($this->serverPath);

        $this->Json($this->Payload());
    }

    /**
     * Mark a notification as read and send the user to its target.
     */
    private function Open(int $id): void
    {
        $userID = (int) $_SESSION['bitboard_user']->id;
        $notification = NotificationController::GetForUserByID($this->database, $id, $userID);

        if (empty($notification))
            throw new PageNotFoundException();

        NotificationController::MarkRead($this->database, $id, $userID);

        $target = (string) ($notification['url'] ?? '');

        if (!$this->IsSafeTarget($target))
            $target = $this->serverPath;

        UrlManager::Redirect($target);
    }

    /**
     * Only redirect to internal, single-line URLs.
     */
    private function IsSafeTarget(string $url): bool
    {
        return $url !== ''
            && str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && strpbrk($url, "\r\n") === false;
    }

    private function HandleSubmission(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!SessionManager::ValidateCsrfToken($token))
            $this->Json(['error' => 'invalid_request'], 403);

        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($action !== 'read_all')
            $this->Json(['error' => 'invalid_action'], 400);

        NotificationController::MarkAllRead($this->database, (int) $_SESSION['bitboard_user']->id);

        $this->Json($this->Payload());
    }

    /**
     * Build the panel payload.
     */
    private function Payload(): array
    {
        $userID = (int) $_SESSION['bitboard_user']->id;
        $rows = NotificationController::GetForUser($this->database, $userID, 0, self::PANEL_LIMIT);
        $items = [];

        foreach ($rows as $row)
        {
            $type = (string) ($row['type'] ?? '');

            if ($type === 'mention')
                $icon = 'ti-at';
            elseif ($type === 'thread_like' || $type === 'post_like')
                $icon = 'ti-heart';
            else
                $icon = 'ti-bell';

            $items[] = [
                'id' => (int) $row['id'],
                'type' => $type,
                'message' => (string) $row['message'],
                'is_read' => (int) ($row['is_read'] ?? 0) === 1,
                'time' => RelativeTime::Convert($row['created_at']),
                'icon' => $icon,
                'open_url' => $this->serverPath . 'notifications/open/' . (int) $row['id'],
            ];
        }

        return [
            'unread' => NotificationController::CountUnread($this->database, $userID),
            'notifications' => $items,
        ];
    }

    public function Do(): void
    {
        // The endpoint responds during construction.
    }
}

?>
