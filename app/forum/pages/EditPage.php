<?php

namespace App\Forum\Pages;

use Exception;

use App\Classes\Database;
use App\Classes\HtmlSanitizer;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Permissions;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\Slug;
use App\Classes\Template;
use App\Classes\Text;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;

use App\Forum\Controllers\ContentController;
use App\Forum\Controllers\MentionController;
use App\Forum\Controllers\PrefixController;
use App\Forum\Controllers\AuditController;
use App\Forum\Widgets\EditorWidget;

use App\Interfaces\PageInterface;

class EditPage extends PageBase implements PageInterface
{
    private const MAX_TITLE_LENGTH = 128;
    private const MAX_CONTENT_LENGTH = 4000;
    private const MAX_STORED_CONTENT_LENGTH = 4096;

    private string $type = '';
    private int $id = 0;
    private int $threadID = 0;
    private array $content = [];

    private bool $canEdit = false;
    private bool $canDelete = false;

    private string $heading = '';
    private string $subheading = '';
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
        $this->forumDesc = 'Edit';
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (!empty($this->forumData->actionParameters[1]))
            throw new PageNotFoundException();

        $isSubmission = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $source = $isSubmission ? $_POST : $_GET;

        $this->type = isset($source['type']) && is_string($source['type']) ? $source['type'] : '';
        $this->id = isset($source['id']) && is_string($source['id']) && ctype_digit($source['id'])
            ? (int) $source['id']
            : 0;

        if (!in_array($this->type, ['thread', 'post'], true) || $this->id <= 0)
            throw new PageNotFoundException();

        if (!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath . 'login');

        if ($this->type === 'thread')
            $this->LoadThread();
        else
            $this->LoadPost();

        if ($this->denied)
            return;

        $this->forumDesc = $this->heading;
        $this->ogTitle = $this->heading . ' - ' . $this->forumName;
        $this->ogDescription = $this->subheading;

        if (!$isSubmission)
            return;

        $this->HandleSubmission();
    }

    private function LoadThread(): void
    {
        $thread = $this->database->Query('SELECT t.*, f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $this->id)->FetchArray();

        if (empty($thread))
            throw new PageNotFoundException();

        $locked = (int) $thread['forum_locked'] === 1 || (int) ($thread['subforum_locked'] ?? 0) === 1;
        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $this->content = $thread;
        $this->threadID = $this->id;
        $this->canEdit = ContentController::CanEditThread((int) $thread['user_id']);
        $this->canDelete = ContentController::CanDeleteThread((int) $thread['user_id']);
        $this->heading = $this->canEdit ? 'Edit thread' : 'Delete thread';
        $this->subheading = ($this->canEdit ? 'Editing “' : 'Deleting “') . $thread['thread_title'] . '”';
        $this->cancelUrl = UrlManager::ThreadUrl($this->id, Slug::FromTitle($thread['thread_title']), 1);
        $this->titleValue = $thread['thread_title'];
        $this->contentValue = Text::FromHtml($thread['thread_content']);
        $this->customPrefixes = PrefixController::GetCustomPrefixes($this->database);
        $this->prefixID = (int) ($thread['prefix_id'] ?? 0);

        if (!$this->IsAllowedPrefix($this->prefixID))
            $this->prefixID = 0;

        if (!$this->canEdit && !$this->canDelete)
            $this->Deny('You do not have permission to edit or delete this thread.');
    }

    private function LoadPost(): void
    {
        $post = $this->database->Query('SELECT p.*, t.thread_title, t.forum_id, t.subforum_id,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_posts AS p
            JOIN bit_threads AS t ON t.id = p.thread_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE p.id = ?', $this->id)->FetchArray();

        if (empty($post))
            throw new PageNotFoundException();

        $locked = (int) $post['forum_locked'] === 1 || (int) ($post['subforum_locked'] ?? 0) === 1;
        if ($locked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $this->content = $post;
        $this->threadID = (int) $post['thread_id'];
        $this->canEdit = ContentController::CanEditPost((int) $post['user_id']);
        $this->canDelete = ContentController::CanDeletePost((int) $post['user_id']);
        $this->heading = $this->canEdit ? 'Edit post' : 'Delete post';
        $this->subheading = ($this->canEdit ? 'Editing a reply in “' : 'Deleting a reply in “') . $post['thread_title'] . '”';
        $this->contentValue = Text::FromHtml($post['post_content']);

        $page = ContentController::PostPageNumber($this->database, $post);
        $this->cancelUrl = UrlManager::ThreadUrl(
            $this->threadID,
            Slug::FromTitle($post['thread_title']),
            $page
        ) . '#post-' . $this->id;

        if (!$this->canEdit && !$this->canDelete)
            $this->Deny('You do not have permission to edit or delete this post.');
    }

    private function Deny(string $message): void
    {
        $this->denied = true;
        $this->deniedMessage = $message;
        $this->forumDesc = 'Unable to edit';
    }

    private function HandleSubmission(): void
    {
        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            $this->error = 'Invalid request. Please try again.';
            return;
        }

        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'save';

        if ($action === 'delete')
        {
            if (!$this->canDelete)
            {
                $this->Deny('You do not have permission to delete this content.');
                return;
            }

            try
            {
                if ($this->type === 'thread')
                {
                    ContentController::DeleteThread($this->database, $this->id);

                    if (SessionManager::IsStaff())
                    {
                        AuditController::Log(
                            $this->database,
                            'thread.delete',
                            sprintf('Deleted thread "%s".', $this->content['thread_title']),
                            'thread',
                            $this->id
                        );
                    }

                    $redirect = (int) ($this->content['subforum_id'] ?? -1) > 0
                        ? $this->serverPath . 'subforum/' . (int) $this->content['subforum_id'] . '/page/1'
                        : $this->serverPath . 'forum/' . (int) $this->content['forum_id'] . '/page/1';

                    UrlManager::Redirect($redirect);
                }

                ContentController::DeletePost($this->database, $this->id);

                if (SessionManager::IsStaff())
                {
                    AuditController::Log(
                        $this->database,
                        'post.delete',
                        sprintf('Deleted post #%d in "%s".', $this->id, $this->content['thread_title']),
                        'post',
                        $this->id
                    );
                }

                UrlManager::Redirect(UrlManager::ThreadUrl(
                    $this->threadID,
                    Slug::FromTitle($this->content['thread_title']),
                    1
                ));
            }
            catch (Exception $e)
            {
                error_log('BitBoard content delete failed: ' . $e->getMessage());
                $this->error = 'Unable to delete this content. Please try again.';
            }

            return;
        }

        if (!$this->canEdit)
        {
            $this->Deny('You do not have permission to edit this content.');
            return;
        }

        $content = isset($_POST['content']) && is_string($_POST['content']) ? trim($_POST['content']) : '';
        $this->contentValue = $content;

        if ($this->type === 'thread')
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
            $editorID = (int) $_SESSION['bitboard_user']->id;

            if ($this->type === 'thread')
            {
                ContentController::UpdateThread($this->database, $this->id, $editorID, $this->titleValue, $html, $this->prefixID);

                if (SessionManager::IsStaff())
                {
                    AuditController::Log(
                        $this->database,
                        'thread.edit',
                        sprintf('Edited thread "%s".', $this->titleValue),
                        'thread',
                        $this->id
                    );
                }

                UrlManager::Redirect(UrlManager::ThreadUrl($this->id, Slug::FromTitle($this->titleValue), 1));
            }

            ContentController::UpdatePost($this->database, $this->id, $editorID, $html);

            if (SessionManager::IsStaff())
            {
                AuditController::Log(
                    $this->database,
                    'post.edit',
                    sprintf('Edited post #%d in "%s".', $this->id, $this->content['thread_title']),
                    'post',
                    $this->id
                );
            }

            $page = ContentController::PostPageNumber($this->database, $this->content);
            UrlManager::Redirect(UrlManager::ThreadUrl(
                $this->threadID,
                Slug::FromTitle($this->content['thread_title']),
                $page
            ) . '#post-' . $this->id);
        }
        catch (Exception $e)
        {
            error_log('BitBoard content update failed: ' . $e->getMessage());
            $this->error = 'Unable to save your changes. Please try again.';
        }
    }

    /**
     * Check whether a prefix ID is an editable custom prefix.
     */
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
        if ($this->type !== 'thread' || !$this->canEdit || count($this->customPrefixes) <= 0)
            return '';

        $options = '<option value="0"' . ($this->prefixID === 0 ? ' selected' : '') . '>No prefix</option>';

        foreach ($this->customPrefixes as $prefix)
        {
            $id = (int) $prefix['id'];
            $selected = $this->prefixID === $id ? ' selected' : '';
            $options .= '<option value="' . $id . '"' . $selected . '>'
                . Template::Escape($prefix['prefix_name']) . '</option>';
        }

        $template = new Template('edit', 'prefix_field');
        $template->AddEntry('{options}', $options);
        $template->Replace();

        return $template->template;
    }

    private function BuildEditForm(): string
    {
        if (!$this->canEdit)
            return '';

        $titleField = '';
        if ($this->type === 'thread')
        {
            $titleTemplate = new Template('post', 'title_field');
            $titleTemplate->AddEscapedEntry('{thread_title}', $this->titleValue);
            $titleTemplate->Replace();
            $titleField = $titleTemplate->template;
        }

        $form = new Template('edit', 'form');
        $form->AddEscapedEntry('{type}', $this->type);
        $form->AddEscapedEntry('{id}', $this->id);
        $form->AddEscapedEntry('{content}', $this->contentValue);
        $form->AddEscapedEntry('{cancel_url}', $this->cancelUrl);
        $form->AddEscapedEntry('{draft_key}', 'u' . (int) ($_SESSION['bitboard_user']->id ?? 0) . '-edit-' . $this->type . '-' . $this->id);
        $form->AddEntry('{editor_toolbar}', (new EditorWidget())->Template);
        $form->AddEntry('{title_field}', $titleField);
        $form->AddEntry('{prefix_field}', $this->BuildPrefixField());
        $form->Replace();

        return $form->template;
    }

    private function BuildDeleteForm(): string
    {
        if (!$this->canDelete)
            return '';

        $confirm = $this->type === 'thread'
            ? 'Delete this thread? All posts inside it will also be deleted. This cannot be undone.'
            : 'Delete this post? This cannot be undone.';

        $form = new Template('edit', 'delete_form');
        $form->AddEscapedEntry('{type}', $this->type);
        $form->AddEscapedEntry('{id}', $this->id);
        $form->AddEscapedEntry('{label}', $this->type);
        $form->AddEscapedEntry('{confirm}', $confirm);
        $form->AddEscapedEntry('{cancel_url}', $this->cancelUrl);
        $form->Replace();

        return $form->template;
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

    private function BuildHistory(): string
    {
        $rows = $this->type === 'thread'
            ? ContentController::GetThreadHistory($this->database, $this->id)
            : ContentController::GetPostHistory($this->database, $this->id);

        if (count($rows) <= 0)
        {
            $empty = new Template('edit', 'history_empty');
            return $empty->template;
        }

        $items = '';
        foreach ($rows as $row)
        {
            $title = '';
            if ($this->type === 'thread')
                $title = '<div class="edit-history-title">' . Template::Escape($row['thread_title']) . '</div>';

            $content = $this->type === 'thread' ? $row['thread_content'] : $row['post_content'];

            $item = new Template('edit', 'history_item');
            $item->AddEntry('{title}', $title);
            $item->AddEntry('{content}', MentionController::Link(
                $this->database,
                HtmlSanitizer::Sanitize($content)
            ));
            $item->AddEntry('{username}', UsernameUtils::Format($row['rank_format'], $row['username']));
            $item->AddEscapedEntry('{date}', RelativeTime::Format($row['edited_at']));
            $item->Replace();
            $items .= $item->template;
        }

        $history = new Template('edit', 'history');
        $history->AddEntry('{items}', $items);
        $history->Replace();

        return $history->template;
    }

    public function Do(): void
    {
        if ($this->denied)
        {
            http_response_code(403);

            $template = new Template('edit', 'denied');
            $template->AddEscapedEntry('{message}', $this->deniedMessage);
            $template->AddEscapedEntry('{back_url}', $this->cancelUrl);
            $template->Replace();
            $this->template = $template;

            parent::RenderPage('edit');
            return;
        }

        $this->template = new Template('edit', 'edit');
        $this->template->AddEscapedEntry('{heading}', $this->heading);
        $this->template->AddEscapedEntry('{subheading}', $this->subheading);
        $this->template->AddEntry('{error}', $this->BuildError());
        $this->template->AddEntry('{edit_form}', $this->BuildEditForm());
        $this->template->AddEntry('{delete_form}', $this->BuildDeleteForm());
        $this->template->AddEntry('{history}', $this->BuildHistory());

        parent::RenderPage('edit');
    }
}

?>
