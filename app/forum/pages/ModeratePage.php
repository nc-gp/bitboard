<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\UrlManager;

use App\Forum\Controllers\ThreadController;

use App\Interfaces\PageInterface;

/**
 * Staff thread moderation endpoint for pin and lock actions.
 */
class ModeratePage extends PageBase implements PageInterface
{
    private int $threadID = 0;
    private int $page = 1;
    private array $thread = [];
    private string $action = '';
    private int $permission = 0;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Moderate';
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

        $this->thread = $this->database->Query('SELECT t.id, t.thread_title, t.is_pinned, t.is_closed,
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

        if (!in_array($this->action, ['pin', 'unpin', 'lock', 'unlock'], true))
            throw new PageNotFoundException();

        $this->permission = in_array($this->action, ['pin', 'unpin'], true)
            ? Permissions::PINNING_THREADS
            : Permissions::LOCKING_THREADS;

        if (!SessionManager::HasPermission($this->permission))
            $this->Forbidden();

        $this->Apply();
    }

    private function Apply(): void
    {
        if ($this->action === 'pin' || $this->action === 'unpin')
            ThreadController::SetPinned($this->database, $this->threadID, $this->action === 'pin');
        else
            ThreadController::SetClosed($this->database, $this->threadID, $this->action === 'lock');

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
