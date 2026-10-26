<?php

namespace App\Forum\Pages;

use Exception;

use App\Classes\Database;
use App\Classes\HtmlSanitizer;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\Template;
use App\Classes\Text;
use App\Classes\UrlManager;

use App\Forum\Controllers\PostController;
use App\Forum\Controllers\MentionController;
use App\Forum\Controllers\PrefixController;
use App\Forum\Controllers\ThreadController;

use App\Interfaces\PageInterface;

class PostPage extends PageBase implements PageInterface
{
    private const MAX_TITLE_LENGTH = 128;
    private const MAX_CONTENT_LENGTH = 4000;
    private const MAX_STORED_CONTENT_LENGTH = 4096;

    private string $mode = '';
    private int $forumID = 0;
    private int $subforumID = -1;
    private int $threadID = 0;
    private int $returnPage = 1;

    private array $thread = [];
    private string $targetForumName = '';
    private string $subforumName = '';

    private string $heading = '';
    private string $subheading = '';
    private string $submitLabel = '';
    private string $titleValue = '';
    private string $contentValue = '';
    private string $error = '';
    private string $cancelUrl = '';
    private int $prefixID = 0;
    private array $customPrefixes = [];

    private bool $denied = false;
    private string $deniedMessage = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Post';
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (!empty($this->forumData->actionParameters[1]))
            throw new PageNotFoundException();

        $isSubmission = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $source = $isSubmission ? $_POST : $_GET;

        $threadID = isset($source['thread']) && is_string($source['thread']) && ctype_digit($source['thread'])
            ? (int) $source['thread']
            : 0;
        $forumID = isset($source['forum']) && is_string($source['forum']) && ctype_digit($source['forum'])
            ? (int) $source['forum']
            : 0;
        $subforumID = isset($source['subforum']) && is_string($source['subforum']) && ctype_digit($source['subforum'])
            ? (int) $source['subforum']
            : 0;
        $page = isset($source['page']) && is_string($source['page']) && ctype_digit($source['page'])
            ? (int) $source['page']
            : 1;
        $quote = isset($source['quote']) && is_string($source['quote']) ? $source['quote'] : '';

        if ($threadID <= 0 && $forumID <= 0)
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        if ($threadID > 0)
        {
            $this->mode = 'reply';
            $this->SetupReply($threadID, $page, $quote);
        }
        else
        {
            $this->mode = 'thread';
            $this->SetupNewThread($forumID, $subforumID);
        }

        if ($this->denied)
            return;

        $this->forumDesc = $this->heading;
        $this->ogTitle = $this->heading . ' - ' . $this->forumName;
        $this->ogDescription = $this->subheading;

        if (!$isSubmission)
            return;

        $this->HandleSubmission();
    }

    private function SetupReply(int $threadID, int $page, string $quote = ''): void
    {
        $thread = $this->database->Query('SELECT t.id, t.thread_title, t.thread_content, t.is_closed, t.forum_id, t.subforum_id,
                u.username AS author_name,
                f.forum_name, f.is_locked AS forum_locked,
                sf.subforum_name, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_accounts AS u ON u.id = t.user_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $threadID)->FetchArray();

        if (empty($thread))
            throw new PageNotFoundException();

        $this->thread = $thread;
        $this->threadID = $threadID;
        $this->forumID = (int) $thread['forum_id'];
        $this->targetForumName = $thread['forum_name'];
        $this->returnPage = max(1, $page);

        $locked = (int) $thread['forum_locked'] === 1 || (int) ($thread['subforum_locked'] ?? 0) === 1;

        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $this->cancelUrl = UrlManager::ThreadUrl(
            $threadID,
            Slug::FromTitle($thread['thread_title']),
            $this->returnPage
        );
        $this->heading = 'Reply to thread';
        $this->subheading = 'Replying to "' . $thread['thread_title'] . '"';
        $this->submitLabel = 'Post reply';

        if ($quote !== '')
            $this->contentValue = $this->BuildQuote($quote);

        if ((int) $thread['is_closed'] === 1 && !SessionManager::IsStaff())
        {
            $this->Deny('This thread is closed and no longer accepts replies.');
            return;
        }

        if (!SessionManager::HasPermission(Permissions::CREATING_POSTS))
        {
            $this->Deny('You do not have permission to reply to threads.');
            return;
        }

        if ($locked && !SessionManager::HasPermission(Permissions::CREATING_POSTS_LOCKED))
        {
            $this->Deny('You do not have permission to reply in this locked forum.');
            return;
        }
    }

    /**
     * Load the requested post or thread opener as a plain-text quote.
     */
    private function BuildQuote(string $quote): string
    {
        if ($quote === 'thread')
            return Text::Quote($this->thread['thread_content'], $this->thread['author_name']);

        if (!ctype_digit($quote) || (int) $quote <= 0)
            return '';

        $post = $this->database->Query('SELECT p.post_content, a.username
            FROM bit_posts AS p
            JOIN bit_accounts AS a ON a.id = p.user_id
            WHERE p.id = ? AND p.thread_id = ?', (int) $quote, $this->threadID)->FetchArray();

        if (empty($post))
            return '';

        return Text::Quote($post['post_content'], $post['username']);
    }

    private function SetupNewThread(int $forumID, int $subforumID): void
    {
        $forum = $this->database->Query('SELECT id, forum_name, is_locked FROM bit_forums WHERE id = ?', $forumID)->FetchArray();

        if (empty($forum))
            throw new PageNotFoundException();

        $this->forumID = (int) $forum['id'];
        $this->targetForumName = $forum['forum_name'];
        $locked = (int) $forum['is_locked'] === 1;

        if ($subforumID > 0)
        {
            $subforum = $this->database->Query('SELECT id, forum_id, subforum_name, is_locked
                FROM bit_subforums WHERE id = ? AND forum_id = ?', $subforumID, $this->forumID)->FetchArray();

            if (empty($subforum))
                throw new PageNotFoundException();

            $this->subforumID = (int) $subforum['id'];
            $this->subforumName = $subforum['subforum_name'];
            $locked = $locked || (int) $subforum['is_locked'] === 1;
        }

        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $this->cancelUrl = $this->serverPath
            . ($this->subforumID > 0 ? 'subforum/' . $this->subforumID : 'forum/' . $this->forumID)
            . '/page/1';
        $this->heading = 'New thread';
        $this->subheading = 'Posting in ' . $this->targetForumName
            . ($this->subforumName !== '' ? ' / ' . $this->subforumName : '');
        $this->submitLabel = 'Create thread';
        $this->customPrefixes = PrefixController::GetCustomPrefixes($this->database);

        if (!SessionManager::HasPermission(Permissions::CREATING_THREADS))
        {
            $this->Deny('You do not have permission to create threads.');
            return;
        }

        if ($locked && !SessionManager::HasPermission(Permissions::CREATING_THREADS_LOCKED))
        {
            $this->Deny('You do not have permission to create threads in this locked forum.');
            return;
        }
    }

    private function Deny(string $message): void
    {
        $this->denied = true;
        $this->deniedMessage = $message;
        $this->forumDesc = 'Unable to post';
    }

    private function HandleSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->error = 'Invalid request. Please try again.';
            return;
        }

        $content = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : '';
        $this->contentValue = $content;

        if ($this->mode === 'thread')
        {
            $title = isset($_POST['thread_title']) && is_string($_POST['thread_title'])
                ? trim($_POST['thread_title'])
                : '';
            $this->titleValue = $title;

            $prefixID = isset($_POST['prefix_id']) && is_string($_POST['prefix_id']) && ctype_digit($_POST['prefix_id'])
                ? (int) $_POST['prefix_id']
                : 0;

            if ($prefixID !== 0 && !$this->IsAllowedPrefix($prefixID))
            {
                $this->error = 'Please choose a valid prefix.';
                return;
            }

            $this->prefixID = $prefixID;

            if (mb_strlen($title) < 3)
            {
                $this->error = 'Title must be at least 3 characters.';
                return;
            }

            if (mb_strlen($title) > self::MAX_TITLE_LENGTH)
            {
                $this->error = 'Title must be 128 characters or fewer.';
                return;
            }
        }

        if ($content === '')
        {
            $this->error = 'Please enter a message.';
            return;
        }

        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH)
        {
            $this->error = 'Your message is too long (maximum 4000 characters).';
            return;
        }

        $html = HtmlSanitizer::Sanitize(Text::ToHtml($content));

        if ($html === '')
        {
            $this->error = 'Please enter a message.';
            return;
        }

        if (mb_strlen($html) > self::MAX_STORED_CONTENT_LENGTH)
        {
            $this->error = 'Your message is too long for the database. Please shorten it.';
            return;
        }

        try
        {
            $userId = (int) $_SESSION['bitboard_user']->id;

            if ($this->mode === 'thread')
            {
                $newThreadID = ThreadController::Create(
                    $this->database,
                    $userId,
                    $this->titleValue,
                    $html,
                    false,
                    false,
                    $this->prefixID,
                    $this->forumID,
                    $this->subforumID
                );

                if ($newThreadID <= 0)
                    throw new Exception('The new thread ID could not be determined.');

                $threadUrl = UrlManager::ThreadUrl($newThreadID, Slug::FromTitle($this->titleValue), 1);

                MentionController::NotifyMentions(
                    $this->database,
                    $userId,
                    (string) $_SESSION['bitboard_user']->username,
                    $content,
                    $newThreadID,
                    0,
                    $this->titleValue,
                    $threadUrl
                );

                UrlManager::Redirect($threadUrl);
            }

            $newPostID = PostController::Create($this->database, $userId, $this->threadID, $html);

            if ($newPostID <= 0)
                throw new Exception('The new post ID could not be determined.');

            $count = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE thread_id = ?', $this->threadID)->FetchArray();
            $totalPages = max(1, (int) ceil(((int) ($count['post_count'] ?? 0)) / PostController::POSTS_PER_PAGE));

            $postUrl = UrlManager::ThreadUrl(
                $this->threadID,
                Slug::FromTitle($this->thread['thread_title']),
                $totalPages
            ) . '#post-' . $newPostID;

            MentionController::NotifyMentions(
                $this->database,
                $userId,
                (string) $_SESSION['bitboard_user']->username,
                $content,
                $this->threadID,
                $newPostID,
                $this->thread['thread_title'],
                $postUrl
            );

            UrlManager::Redirect($postUrl);
        }
        catch (Exception $e)
        {
            error_log('BitBoard post creation failed: ' . $e->getMessage());
            $this->error = 'Unable to save your post. Please try again.';
        }
    }

    private function BuildContextFields(): string
    {
        if ($this->mode === 'reply')
        {
            return '<input type="hidden" name="thread" value="' . $this->threadID . '">'
                . '<input type="hidden" name="page" value="' . $this->returnPage . '">';
        }

        $fields = '<input type="hidden" name="forum" value="' . $this->forumID . '">';

        if ($this->subforumID > 0)
            $fields .= '<input type="hidden" name="subforum" value="' . $this->subforumID . '">';

        return $fields;
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

    private function IsAllowedPrefix(int $prefixID): bool
    {
        if ($prefixID <= 2)
            return $prefixID === 0;

        foreach ($this->customPrefixes as $prefix)
        {
            if ((int) $prefix['id'] === $prefixID)
                return true;
        }

        return false;
    }

    private function BuildPrefixField(): string
    {
        if ($this->mode !== 'thread' || count($this->customPrefixes) <= 0)
            return '';

        $options = '<option value="0"' . ($this->prefixID === 0 ? ' selected' : '') . '>No prefix</option>';

        foreach ($this->customPrefixes as $prefix)
        {
            $id = (int) $prefix['id'];
            $selected = $this->prefixID === $id ? ' selected' : '';
            $options .= '<option value="' . $id . '"' . $selected . '>'
                . Template::Escape($prefix['prefix_name']) . '</option>';
        }

        $template = new Template('post', 'prefix_field');
        $template->AddEntry('{options}', $options);
        $template->Replace();

        return $template->template;
    }

    private function BuildTitleField(): string
    {
        if ($this->mode !== 'thread')
            return '';

        $template = new Template('post', 'title_field');
        $template->AddEscapedEntry('{thread_title}', $this->titleValue);
        $template->Replace();

        return $template->template;
    }

    public function Do(): void
    {
        if ($this->denied)
        {
            http_response_code(403);

            $template = new Template('post', 'denied');
            $template->AddEscapedEntry('{message}', $this->deniedMessage);
            $template->AddEscapedEntry('{back_url}', $this->cancelUrl);
            $template->Replace();
            $this->template = $template;

            parent::RenderPage('post');
            return;
        }

        $this->template = new Template('post', 'post');
        $this->template->AddEscapedEntry('{heading}', $this->heading);
        $this->template->AddEscapedEntry('{subheading}', $this->subheading);
        $this->template->AddEscapedEntry('{submit_label}', $this->submitLabel);
        $this->template->AddEscapedEntry('{cancel_url}', $this->cancelUrl);
        $this->template->AddEscapedEntry('{content}', $this->contentValue);
        $this->template->AddEntry('{title_field}', $this->BuildTitleField());
        $this->template->AddEntry('{prefix_field}', $this->BuildPrefixField());
        $this->template->AddEntry('{context_fields}', $this->BuildContextFields());
        $this->template->AddEntry('{error}', $this->BuildError());

        parent::RenderPage('post');
    }
}

?>
