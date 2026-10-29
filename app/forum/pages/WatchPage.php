<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\UrlManager;

use App\Forum\Controllers\ForumSubscriptionController;

use App\Interfaces\PageInterface;

/**
 * Subscribe/unsubscribe endpoint for forum and subforum watch notifications.
 */
class WatchPage extends PageBase implements PageInterface
{
    private int $forumID = 0;
    private int $subforumID = -1;
    private int $page = 1;
    private string $action = '';
    private array $forum = [];
    private array $subforum = [];

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Watch forum';
        $this->UrlHandler();
    }

    private function Forbidden(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }

    private function UrlHandler(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
            throw new PageNotFoundException();

        if (count($this->forumData->actionParameters) !== 1)
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        $this->forumID = isset($_POST['forum']) && is_string($_POST['forum']) && ctype_digit($_POST['forum'])
            ? (int) $_POST['forum']
            : 0;
        $this->subforumID = isset($_POST['subforum']) && is_string($_POST['subforum']) && preg_match('/^-?\d+$/', $_POST['subforum'])
            ? (int) $_POST['subforum']
            : -1;
        $this->page = isset($_POST['page']) && is_string($_POST['page']) && ctype_digit($_POST['page'])
            ? max(1, (int) $_POST['page'])
            : 1;
        $this->action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($this->forumID <= 0)
            throw new PageNotFoundException();

        $this->forum = $this->database->Query('SELECT id, forum_name, is_locked
            FROM bit_forums WHERE id = ?', $this->forumID)->FetchArray();

        if (empty($this->forum))
            throw new PageNotFoundException();

        $locked = (int) $this->forum['is_locked'] === 1;

        if ($this->subforumID > 0)
        {
            $this->subforum = $this->database->Query('SELECT id, forum_id, subforum_name, is_locked
                FROM bit_subforums WHERE id = ? AND forum_id = ?',
                $this->subforumID, $this->forumID)->FetchArray();

            if (empty($this->subforum))
                throw new PageNotFoundException();

            $locked = $locked || (int) $this->subforum['is_locked'] === 1;
        }
        else
        {
            $this->subforumID = -1;
        }

        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            $this->Forbidden();

        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
            $this->Forbidden();

        $userID = (int) $_SESSION['bitboard_user']->id;

        if ($this->action === 'subscribe')
            ForumSubscriptionController::Watch($this->database, $userID, $this->forumID, $this->subforumID);
        elseif ($this->action === 'unsubscribe')
            ForumSubscriptionController::Unwatch($this->database, $userID, $this->forumID, $this->subforumID);
        else
            throw new PageNotFoundException();

        $target = $this->subforumID > 0
            ? 'subforum/' . $this->subforumID
            : 'forum/' . $this->forumID;

        UrlManager::Redirect($this->serverPath . $target . '/page/' . $this->page);
    }

    public function Do(): void
    {
        // The endpoint responds during construction.
    }
}
