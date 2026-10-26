<?php

namespace App\Forum\Pages;

use Throwable;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\Reactions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\UrlManager;

use App\Forum\Controllers\LikeController;
use App\Forum\Controllers\NotificationController;

use App\Interfaces\PageInterface;

class LikePage extends PageBase implements PageInterface
{
    private bool $isAjax = false;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Like';
        $this->isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

        $this->UrlHandler();
    }

    private function Json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

    private function UrlHandler(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
        {
            if ($this->isAjax)
                $this->Json(['error' => 'login_required'], 401);

            UrlManager::Redirect($this->serverPath . 'login');
        }

        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            if ($this->isAjax)
                $this->Json(['error' => 'invalid_request'], 403);

            UrlManager::Redirect($this->serverPath);
        }

        $type = isset($_POST['type']) && is_string($_POST['type']) ? $_POST['type'] : '';
        $id = isset($_POST['id']) && is_string($_POST['id']) && ctype_digit($_POST['id'])
            ? (int) $_POST['id']
            : 0;
        $page = isset($_POST['page']) && is_string($_POST['page']) && ctype_digit($_POST['page'])
            ? max(1, (int) $_POST['page'])
            : 1;

        if (!in_array($type, ['thread', 'post'], true) || $id <= 0)
        {
            if ($this->isAjax)
                $this->Json(['error' => 'invalid_target'], 400);

            throw new PageNotFoundException();
        }

        if ($type === 'thread')
        {
            $target = $this->database->Query('SELECT t.id, t.thread_title, t.user_id AS author_id, t.forum_id, t.subforum_id,
                    f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
                FROM bit_threads AS t
                JOIN bit_forums AS f ON f.id = t.forum_id
                LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
                WHERE t.id = ?', $id)->FetchArray();

            $threadID = $id;
        }
        else
        {
            $target = $this->database->Query('SELECT p.id, p.thread_id, p.user_id AS author_id, t.thread_title, t.forum_id, t.subforum_id,
                    f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
                FROM bit_posts AS p
                JOIN bit_threads AS t ON t.id = p.thread_id
                JOIN bit_forums AS f ON f.id = t.forum_id
                LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
                WHERE p.id = ?', $id)->FetchArray();

            $threadID = (int) ($target['thread_id'] ?? 0);
        }

        if (empty($target) || $threadID <= 0)
        {
            if ($this->isAjax)
                $this->Json(['error' => 'not_found'], 404);

            throw new PageNotFoundException();
        }

        $locked = (int) $target['forum_locked'] === 1 || (int) ($target['subforum_locked'] ?? 0) === 1;
        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
        {
            if ($this->isAjax)
                $this->Json(['error' => 'forbidden'], 403);

            UrlManager::Redirect($this->serverPath);
        }

        LikeController::EnsureSchema($this->database);

        $userId = (int) $_SESSION['bitboard_user']->id;
        $threadUrl = UrlManager::ThreadUrl($threadID, Slug::FromTitle($target['thread_title']), $page);

        $reaction = isset($_POST['reaction']) && is_string($_POST['reaction'])
            ? $_POST['reaction']
            : '';

        if (!Reactions::IsValid($reaction))
        {
            if ($this->isAjax)
                $this->Json(['error' => 'invalid_reaction'], 400);

            $targetUrl = $threadUrl . ($type === 'post' ? '#post-' . $id : '');
            UrlManager::Redirect($targetUrl);
        }

        try
        {
            if ($type === 'thread')
                $result = LikeController::ToggleThreadReaction($this->database, $userId, $id, $reaction);
            else
                $result = LikeController::TogglePostReaction($this->database, $userId, $id, $reaction);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard like toggle failed: ' . $e->getMessage());

            if ($this->isAjax)
                $this->Json(['error' => 'like_failed'], 500);

            UrlManager::Redirect($threadUrl);
        }

        $notificationType = $type === 'thread' ? 'thread_like' : 'post_like';
        $notificationPostID = $type === 'post' ? $id : 0;
        $targetUrl = $threadUrl . ($type === 'post' ? '#post-' . $id : '');

        if (!empty($result['added']))
        {
            NotificationController::NotifyReaction(
                $this->database,
                $userId,
                (string) $_SESSION['bitboard_user']->username,
                (int) $target['author_id'],
                $notificationType,
                $threadID,
                $notificationPostID,
                $target['thread_title'],
                $targetUrl
            );
        }
        elseif (empty($result['liked']))
        {
            NotificationController::DeleteReaction(
                $this->database,
                $userId,
                $notificationType,
                $threadID,
                $notificationPostID
            );
        }

        if ($this->isAjax)
            $this->Json($result);

        if ($type === 'post')
            $threadUrl .= '#post-' . $id;

        UrlManager::Redirect($threadUrl);
    }

    public function Do(): void
    {
        // The like endpoint responds during construction.
    }
}

?>
