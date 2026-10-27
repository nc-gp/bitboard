<?php

namespace App\Forum\Pages;

use Throwable;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\Template;
use App\Classes\UrlManager;

use App\Forum\Controllers\ContentController;
use App\Forum\Controllers\ReportController;

use App\Interfaces\PageInterface;

/**
 * Report form for threads and replies.
 */
class ReportPage extends PageBase implements PageInterface
{
    private const MAX_DETAILS = 1000;

    private string $type = '';
    private int $id = 0;
    private int $threadID = 0;
    private int $page = 1;
    private int $authorID = 0;
    private string $threadTitle = '';
    private string $cancelUrl = '';

    private string $reason = '';
    private string $details = '';
    private string $error = '';
    private string $notice = '';
    private bool $success = false;
    private bool $blocked = false;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Report content';
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (count($this->forumData->actionParameters) !== 1)
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        $isSubmission = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $source = $isSubmission ? $_POST : $_GET;

        $this->type = isset($source['type']) && is_string($source['type']) ? $source['type'] : '';
        $this->id = isset($source['id']) && is_string($source['id']) && ctype_digit($source['id'])
            ? (int) $source['id']
            : 0;
        $this->page = isset($source['page']) && is_string($source['page']) && ctype_digit($source['page'])
            ? max(1, (int) $source['page'])
            : 1;

        if (!in_array($this->type, ['thread', 'post'], true) || $this->id <= 0)
            throw new PageNotFoundException();

        if ($this->type === 'thread')
            $this->LoadThread();
        else
            $this->LoadPost();

        if (SessionManager::IsLogged() && (int) $_SESSION['bitboard_user']->id === $this->authorID)
        {
            $this->blocked = true;
            $this->notice = 'You cannot report your own content.';
            return;
        }

        $userID = (int) $_SESSION['bitboard_user']->id;
        $postID = $this->type === 'post' ? $this->id : 0;

        if (ReportController::HasOpenReport($this->database, $userID, $this->threadID, $postID))
        {
            $this->blocked = true;
            $this->notice = 'You have already reported this content. Forum staff will review it.';
            return;
        }

        if (!$isSubmission)
            return;

        $this->HandleSubmission($userID, $postID);
    }

    private function LoadThread(): void
    {
        $thread = $this->database->Query('SELECT t.id, t.thread_title, t.user_id, t.forum_id, t.subforum_id,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $this->id)->FetchArray();

        if (empty($thread))
            throw new PageNotFoundException();

        $this->GuardForum($thread);

        $this->threadID = $this->id;
        $this->authorID = (int) $thread['user_id'];
        $this->threadTitle = $thread['thread_title'];
        $this->cancelUrl = UrlManager::ThreadUrl($this->threadID, Slug::FromTitle($this->threadTitle), $this->page);
    }

    private function LoadPost(): void
    {
        $post = $this->database->Query('SELECT p.id, p.thread_id, p.user_id, p.post_timestamp,
                t.thread_title, t.forum_id, t.subforum_id,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_posts AS p
            JOIN bit_threads AS t ON t.id = p.thread_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE p.id = ?', $this->id)->FetchArray();

        if (empty($post))
            throw new PageNotFoundException();

        $this->GuardForum($post);

        $this->threadID = (int) $post['thread_id'];
        $this->authorID = (int) $post['user_id'];
        $this->threadTitle = $post['thread_title'];

        $page = ContentController::PostPageNumber($this->database, $post);
        $this->cancelUrl = UrlManager::ThreadUrl(
            $this->threadID,
            Slug::FromTitle($this->threadTitle),
            $page
        ) . '#post-' . $this->id;
    }

    private function GuardForum(array $target): void
    {
        $locked = (int) $target['forum_locked'] === 1 || (int) ($target['subforum_locked'] ?? 0) === 1;

        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);
    }

    private function HandleSubmission(int $userID, int $postID): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->error = 'Invalid request. Please try again.';
            return;
        }

        $this->reason = isset($_POST['reason']) && is_string($_POST['reason']) ? $_POST['reason'] : '';
        $this->details = isset($_POST['details']) && is_string($_POST['details']) ? trim($_POST['details']) : '';

        if (!array_key_exists($this->reason, ReportController::REASONS))
        {
            $this->error = 'Please choose a reason for the report.';
            return;
        }

        if (mb_strlen($this->details) > self::MAX_DETAILS)
        {
            $this->error = 'Details must be 1000 characters or fewer.';
            return;
        }

        try
        {
            ReportController::Create(
                $this->database,
                $userID,
                $this->threadID,
                $postID,
                $this->reason,
                $this->details
            );

            $this->success = true;
        }
        catch (Throwable $e)
        {
            error_log('BitBoard report creation failed: ' . $e->getMessage());
            $this->error = 'Unable to submit this report. Please try again.';
        }
    }

    private function BuildReasonOptions(): string
    {
        $options = '<option value="">Choose a reason…</option>';

        foreach (ReportController::REASONS as $value => $label)
        {
            $options .= '<option value="' . Template::Escape($value) . '"'
                . ($this->reason === $value ? ' selected' : '') . '>'
                . Template::Escape($label) . '</option>';
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

    private function BuildContent(): string
    {
        if ($this->success || $this->blocked)
        {
            $template = new Template('report', 'notice');
            $template->AddEscapedEntry('{icon}', $this->success ? 'ti-flag-check' : 'ti-info-circle');
            $template->AddEscapedEntry('{title}', $this->success ? 'Report submitted' : 'Report not submitted');
            $template->AddEscapedEntry('{message}', $this->success
                ? 'Thank you. Forum staff will review this report.'
                : $this->notice);
            $template->AddEscapedEntry('{back_url}', $this->cancelUrl);
            $template->AddEscapedEntry('{back_label}', 'Back to thread');
            $template->Replace();

            return $template->template;
        }

        $template = new Template('report', 'form');
        $template->AddEscapedEntry('{type}', $this->type);
        $template->AddEscapedEntry('{id}', $this->id);
        $template->AddEscapedEntry('{page}', $this->page);
        $template->AddEntry('{reason_options}', $this->BuildReasonOptions());
        $template->AddEscapedEntry('{details}', $this->details);
        $template->AddEscapedEntry('{cancel_url}', $this->cancelUrl);
        $template->Replace();

        return $template->template;
    }

    public function Do(): void
    {
        $this->template = new Template('report', 'report');
        $this->template->AddEscapedEntry('{heading}', 'Report content');
        $this->template->AddEscapedEntry('{subheading}', $this->type === 'thread'
            ? 'Reporting “' . $this->threadTitle . '”'
            : 'Reporting a reply in “' . $this->threadTitle . '”');
        $this->template->AddEntry('{error}', $this->BuildError());
        $this->template->AddEntry('{content}', $this->BuildContent());

        parent::RenderPage('report');
    }
}

?>
