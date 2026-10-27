<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\UrlManager;

use App\Forum\Controllers\SubscriptionController;

use App\Interfaces\PageInterface;

/**
 * Subscribe/unsubscribe endpoint for thread reply notifications.
 */
class SubscribePage extends PageBase implements PageInterface
{
    private int $threadID = 0;
    private int $page = 1;
    private string $action = '';
    private array $thread = [];

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Subscribe';
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

        $this->threadID = isset($_POST['thread']) && is_string($_POST['thread']) && ctype_digit($_POST['thread'])
            ? (int) $_POST['thread']
            : 0;
        $this->page = isset($_POST['page']) && is_string($_POST['page']) && ctype_digit($_POST['page'])
            ? max(1, (int) $_POST['page'])
            : 1;
        $this->action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($this->threadID <= 0)
            throw new PageNotFoundException();

        $this->thread = $this->database->Query('SELECT t.id, t.thread_title, t.forum_id, t.subforum_id,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $this->threadID)->FetchArray();

        if (empty($this->thread))
            throw new PageNotFoundException();

        $locked = (int) $this->thread['forum_locked'] === 1 || (int) ($this->thread['subforum_locked'] ?? 0) === 1;
        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            $this->Forbidden();

        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
            $this->Forbidden();

        $userID = (int) $_SESSION['bitboard_user']->id;

        if ($this->action === 'subscribe')
            SubscriptionController::Subscribe($this->database, $userID, $this->threadID);
        elseif ($this->action === 'unsubscribe')
            SubscriptionController::Unsubscribe($this->database, $userID, $this->threadID);
        else
            throw new PageNotFoundException();

        UrlManager::Redirect(UrlManager::ThreadUrl(
            $this->threadID,
            Slug::FromTitle($this->thread['thread_title']),
            $this->page
        ));
    }

    public function Do(): void
    {
        // The endpoint responds during construction.
    }
}

?>
