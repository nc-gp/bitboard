<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\Template;
use App\Classes\UrlManager;

use App\Forum\Controllers\ThreadController;

use App\Interfaces\PageInterface;

/**
 * Staff form for moving a thread to another forum or subforum.
 */
class MovePage extends PageBase implements PageInterface
{
    private int $threadID = 0;
    private array $thread = [];
    private string $error = '';
    private string $cancelUrl = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Move thread';
        $this->UrlHandler();
    }

    private function Forbidden(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }

    private function CanMove(): bool
    {
        return SessionManager::HasPermission(Permissions::PINNING_THREADS)
            || SessionManager::HasPermission(Permissions::LOCKING_THREADS);
    }

    private function UrlHandler(): void
    {
        if (count($this->forumData->actionParameters) !== 1)
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        if (!$this->CanMove())
            $this->Forbidden();

        $isSubmission = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $source = $isSubmission ? $_POST : $_GET;

        $this->threadID = isset($source['thread']) && is_string($source['thread']) && ctype_digit($source['thread'])
            ? (int) $source['thread']
            : 0;

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

        $this->cancelUrl = UrlManager::ThreadUrl(
            $this->threadID,
            Slug::FromTitle($this->thread['thread_title']),
            1
        );

        if (!$isSubmission)
            return;

        $this->HandleSubmission();
    }

    /**
     * @return array [forum ID, subforum ID]
     */
    private function ParseDestination(string $destination): array
    {
        if (!preg_match('/^([fs]):(\d+)$/', $destination, $match))
            return [0, 0];

        $id = (int) $match[2];

        if ($id <= 0)
            return [0, 0];

        if ($match[1] === 'f')
        {
            $forum = $this->database->Query('SELECT id, is_locked FROM bit_forums WHERE id = ?', $id)->FetchArray();

            if (empty($forum))
                return [0, 0];

            if ((int) $forum['is_locked'] === 1 && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
                return [0, 0];

            return [$id, -1];
        }

        $subforum = $this->database->Query('SELECT sf.id, sf.forum_id, sf.is_locked,
                f.is_locked AS forum_locked
            FROM bit_subforums AS sf
            JOIN bit_forums AS f ON f.id = sf.forum_id
            WHERE sf.id = ?', $id)->FetchArray();

        if (empty($subforum))
            return [0, 0];

        $locked = (int) $subforum['forum_locked'] === 1 || (int) $subforum['is_locked'] === 1;
        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            return [0, 0];

        return [(int) $subforum['forum_id'], (int) $subforum['id']];
    }

    private function HandleSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->error = 'Invalid request. Please try again.';
            return;
        }

        $destination = isset($_POST['destination']) && is_string($_POST['destination'])
            ? $_POST['destination']
            : '';

        [$forumID, $subforumID] = $this->ParseDestination($destination);

        if ($forumID <= 0)
        {
            $this->error = 'Please choose a valid destination.';
            return;
        }

        try
        {
            ThreadController::Move($this->database, $this->threadID, $forumID, $subforumID);
            UrlManager::Redirect(UrlManager::ThreadUrl(
                $this->threadID,
                Slug::FromTitle($this->thread['thread_title']),
                1
            ));
        }
        catch (\Throwable $e)
        {
            error_log('BitBoard thread move failed: ' . $e->getMessage());
            $this->error = 'Unable to move this thread. Please try again.';
        }
    }

    private function BuildDestinations(): string
    {
        $forums = $this->database->Query('SELECT id, forum_name, is_locked
            FROM bit_forums ORDER BY forum_position ASC, id ASC')->FetchAll();
        $subforums = $this->database->Query('SELECT id, forum_id, subforum_name, is_locked
            FROM bit_subforums ORDER BY forum_id ASC, id ASC')->FetchAll();

        $canViewLocked = SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED);
        $current = (int) $this->thread['subforum_id'] > 0
            ? 's:' . (int) $this->thread['subforum_id']
            : 'f:' . (int) $this->thread['forum_id'];

        $options = '';

        foreach ($forums as $forum)
        {
            if ((int) $forum['is_locked'] === 1 && !$canViewLocked)
                continue;

            $options .= '<optgroup label="' . Template::Escape($forum['forum_name']) . '">';

            $value = 'f:' . (int) $forum['id'];
            $options .= '<option value="' . $value . '"' . ($current === $value ? ' selected' : '') . '>'
                . Template::Escape($forum['forum_name']) . ' (top level)</option>';

            foreach ($subforums as $subforum)
            {
                if ((int) $subforum['forum_id'] !== (int) $forum['id'])
                    continue;

                if ((int) $subforum['is_locked'] === 1 && !$canViewLocked)
                    continue;

                $value = 's:' . (int) $subforum['id'];
                $options .= '<option value="' . $value . '"' . ($current === $value ? ' selected' : '') . '>'
                    . Template::Escape($forum['forum_name']) . ' / '
                    . Template::Escape($subforum['subforum_name']) . '</option>';
            }

            $options .= '</optgroup>';
        }

        return $options;
    }

    private function BuildError(): string
    {
        if ($this->error === '')
            return '';

        $template = new Template('post', 'error');
        $template->AddEscapedEntry('{message}', $this->error);
        $template->Replace();

        return $template->template;
    }

    public function Do(): void
    {
        $this->template = new Template('move', 'move');
        $this->template->AddEscapedEntry('{heading}', 'Move thread');
        $this->template->AddEscapedEntry('{subheading}', 'Moving “' . $this->thread['thread_title'] . '”');
        $this->template->AddEscapedEntry('{thread_id}', $this->threadID);
        $this->template->AddEscapedEntry('{cancel_url}', $this->cancelUrl);
        $this->template->AddEntry('{error}', $this->BuildError());
        $this->template->AddEntry('{destinations}', $this->BuildDestinations());

        parent::RenderPage('move');
    }
}

?>
