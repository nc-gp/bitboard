<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Template;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\RelativeTime;
use App\Classes\Slug;
use App\Classes\Text;
use App\Classes\UrlManager;
use App\Classes\HtmlSanitizer;
use App\Classes\Permissions;
use App\Classes\SessionManager;

use App\Interfaces\PageInterface;

use App\Forum\Controllers\ContentController;
use App\Forum\Controllers\LikeController;
use App\Forum\Controllers\MentionController;
use App\Forum\Controllers\PostController;
use App\Forum\Controllers\SubscriptionController;
use App\Forum\Controllers\ThreadReadController;
use App\Forum\Widgets\PaginationWidget;
use App\Forum\Widgets\PrefixWidget;
use App\Forum\Widgets\ReactionWidget;

class ThreadPage extends PageBase implements PageInterface
{
    private string $thread = '';
    private string $posts = '';
    private string $replyArea = '';

    private int $threadID;
    private int $threadPage;
    private string $threadSlug = '';
    private bool $threadClosed = false;
    private bool $threadLocked = false;
    private bool $threadPinned = false;
    private bool $canLike = false;

    private int $maximumResults = PostController::POSTS_PER_PAGE;
    private int $postsCount = 0;
    private int $lastPostID = 0;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->canLike = SessionManager::IsLogged();
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        $parameters = $this->forumData->actionParameters;
        $threadID = 0;
        $threadPage = 0;
        $requestedSlug = '';

        if (
            count($parameters) >= 5 &&
            ($parameters[3] ?? '') === 'page' &&
            is_numeric($parameters[1] ?? '') &&
            is_numeric($parameters[4] ?? '')
        )
        {
            $threadID = (int) $parameters[1];
            $requestedSlug = (string) ($parameters[2] ?? '');
            $threadPage = (int) $parameters[4];
        }
        elseif (
            count($parameters) >= 4 &&
            ($parameters[2] ?? '') === 'page' &&
            is_numeric($parameters[1] ?? '') &&
            is_numeric($parameters[3] ?? '')
        )
        {
            // Legacy /thread/{id}/page/{page} URLs redirect to the slug URL below.
            $threadID = (int) $parameters[1];
            $threadPage = (int) $parameters[3];
        }
        else
            throw new PageNotFoundException();

        if ($threadPage <= 0 || $threadID <= 0)
            throw new PageNotFoundException();

        $this->threadID = $threadID;
        $this->threadPage = $threadPage;

        $thread = $this->database->Query('SELECT t.id, t.thread_title, t.thread_content, t.is_closed, t.is_pinned, t.prefix_id,
                u.username AS author_name, u.avatar AS author_avatar,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_accounts AS u ON u.id = t.user_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $this->threadID)->FetchArray();
        if (empty($thread))
            throw new PageNotFoundException();

        $isLocked = (int) $thread['forum_locked'] === 1 || (int) ($thread['subforum_locked'] ?? 0) === 1;
        if ($isLocked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $this->threadClosed = (int) $thread['is_closed'] === 1;
        $this->threadLocked = $isLocked;
        $this->threadPinned = (int) $thread['is_pinned'] === 1;

        $this->threadSlug = Slug::FromTitle($thread['thread_title']);
        $this->forumDesc = $thread['thread_title'];
        $this->ogType = 'article';
        $this->ogTitle = $thread['thread_title'];
        $this->ogDescription = Text::Excerpt($thread['thread_content'], 160);
        $this->ogImage = AvatarUtils::GetPath($thread['author_avatar']);

        if ($this->ogDescription === '')
            $this->ogDescription = 'Thread by ' . $thread['author_name'] . ' on ' . $this->forumName . '.';

        if ($requestedSlug !== $this->threadSlug)
            UrlManager::RedirectPermanent(UrlManager::ThreadUrl($this->threadID, $this->threadSlug, $this->threadPage));

        $this->canonicalUrl = UrlManager::ThreadUrl($this->threadID, $this->threadSlug, $this->threadPage);

        $postCount = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE thread_id = ?', $this->threadID)->FetchArray();
        $this->postsCount = (int) ($postCount['post_count'] ?? 0);

        $totalPages = max(1, (int) ceil($this->postsCount / $this->maximumResults));
        if ($this->threadPage > $totalPages)
            UrlManager::Redirect(UrlManager::ThreadUrl($this->threadID, $this->threadSlug, $totalPages));
    }

    private function fetchThread()
    {
        if($this->threadPage > 1)
            return;

        $thread = $this->database->Query('SELECT t.*, u.username AS user_name, u.avatar AS user_avatar, u.reputation AS user_reputation, u.last_active AS user_lastactive, r.id AS rank_id, r.rank_name, r.rank_format
                                    FROM bit_threads t
                                    JOIN bit_accounts u ON t.user_id = u.id
                                    JOIN bit_ranks r ON u.rank_id = r.id
                                    WHERE t.id = ?', $this->threadID)->FetchArray();

        $userStats = $this->fetchUserStats([$thread['user_id']]);
        $threadUserStats = $userStats[(int) $thread['user_id']] ?? ['thread_count' => 0, 'post_count' => 0];
        $userPostCount = $threadUserStats['thread_count'] + $threadUserStats['post_count'];

        $userStatsTemplate = new Template('thread', 'thread_user_stats');
        $userStatsTemplate->AddEscapedEntry('{user_reputation}', $thread['user_reputation']);
        $userStatsTemplate->AddEscapedEntry('{user_postcount}', $userPostCount);
        $userStatsTemplate->Replace();

        $threadContentTemplate = new Template('thread', 'thread_content');

        $threadPrefix = '';
        if ((int) ($thread['prefix_id'] ?? 0) > 2)
            $threadPrefix = (new PrefixWidget($this->database, (int) $thread['prefix_id']))->template;

        $threadContentTemplate->AddEntry('{thread_prefix}', $threadPrefix);
        $threadContentTemplate->AddEscapedEntry('{thread_title}', $thread['thread_title']);
        $threadContentTemplate->AddEscapedEntry('{thread_timestamp}', 'Published ' . RelativeTime::Format($thread['thread_timestamp']));
        $threadContentTemplate->AddEscapedEntry('{thread_edited}', $this->EditedLabel($thread['thread_timestamp'], $thread['thread_edited_timestamp']));
        $threadContentTemplate->AddEntry('{thread_content}', MentionController::Link(
            $this->database,
            HtmlSanitizer::Sanitize($thread['thread_content'])
        ));
        $threadContentTemplate->AddEntry('{thread_moderation}', $this->BuildModeration());

        $threadQuote = '';
        if ($this->CanReply())
        {
            $threadQuote = $this->BuildQuoteControls(
                'thread',
                Text::Quote($thread['thread_content'], $thread['user_name']),
                'thread'
            );
        }
        $threadContentTemplate->AddEntry('{thread_quote}', $threadQuote);

        $threadEdit = '';
        $canEditThread = ContentController::CanEditThread((int) $thread['user_id']);
        $canDeleteThread = ContentController::CanDeleteThread((int) $thread['user_id']);

        if ($canEditThread || $canDeleteThread)
        {
            $editTemplate = new Template('thread', 'edit_link');
            $editTemplate->AddEscapedEntry('{edit_url}', $this->serverPath . 'edit?type=thread&id=' . $this->threadID);
            $editTemplate->AddEscapedEntry('{label}', $canEditThread ? 'Edit' : 'Delete');
            $editTemplate->AddEscapedEntry('{icon}', $canEditThread ? 'ti-pencil' : 'ti-trash');
            $editTemplate->Replace();
            $threadEdit = $editTemplate->template;
        }
        $threadContentTemplate->AddEntry('{thread_edit}', $threadEdit);

        $threadSubscribe = '';
        if (SessionManager::IsLogged())
        {
            $subscribed = SubscriptionController::IsSubscribed(
                $this->database,
                (int) $_SESSION['bitboard_user']->id,
                $this->threadID
            );

            $subscribeTemplate = new Template('thread', 'subscribe');
            $subscribeTemplate->AddEscapedEntry('{thread_id}', $this->threadID);
            $subscribeTemplate->AddEscapedEntry('{page}', $this->threadPage);
            $subscribeTemplate->AddEscapedEntry('{action}', $subscribed ? 'unsubscribe' : 'subscribe');
            $subscribeTemplate->AddEscapedEntry('{label}', $subscribed ? 'Subscribed' : 'Subscribe');
            $subscribeTemplate->AddEscapedEntry('{icon}', $subscribed ? 'ti-bell-check' : 'ti-bell-plus');
            $subscribeTemplate->AddEscapedEntry('{state_class}', $subscribed ? ' is-active' : '');
            $subscribeTemplate->AddEscapedEntry('{csrf_token}', SessionManager::CsrfToken());
            $subscribeTemplate->Replace();
            $threadSubscribe = $subscribeTemplate->template;
        }
        $threadContentTemplate->AddEntry('{thread_subscribe}', $threadSubscribe);
        $threadContentTemplate->AddEntry('{thread_report}', $this->BuildReportLink(
            'thread',
            $this->threadID,
            $this->threadPage,
            (int) $thread['user_id']
        ));

        LikeController::EnsureSchema($this->database);
        $userId = $this->canLike ? (int) $_SESSION['bitboard_user']->id : null;
        [$threadReactionCounts, $threadUserReaction, $threadReactionTotal] = LikeController::GetThreadReactions(
            $this->database,
            $this->threadID,
            $userId
        );

        $reactionWidget = new ReactionWidget(
            'thread',
            $this->threadID,
            $this->threadPage,
            $threadReactionTotal,
            $threadReactionCounts,
            $threadUserReaction,
            $this->canLike
        );
        $threadContentTemplate->AddEntry('{thread_like}', $reactionWidget->Template);

        $threadContentTemplate->AddEscapedEntry('{user_id}', $thread['user_id']);
        $threadContentTemplate->AddEntry('{user_name}', UsernameUtils::Format($thread['rank_format'], $thread['user_name']));
        $threadContentTemplate->AddEntry('{user_avatar}', AvatarUtils::GetPath($thread['user_avatar']));
        $threadContentTemplate->AddEscapedEntry('{user_avatar_alt}', $thread['user_name']);
        $threadContentTemplate->AddEscapedEntry('{user_rank}', $thread['rank_name']);
        $threadContentTemplate->AddEntry('{user_stats}', $userStatsTemplate->template);
        $threadContentTemplate->Replace();

        $this->thread = $threadContentTemplate->template;
    }

    private function fetchPosts()
    {
        $posts = $this->database->Query('SELECT p.*, a.id AS author_id, a.username, a.avatar, a.reputation, a.signature, r.rank_name, r.rank_format
            FROM bit_posts AS p
            JOIN bit_accounts AS a ON p.user_id = a.id
            JOIN bit_ranks AS r ON a.rank_id = r.id
            WHERE p.thread_id = ?
            ORDER BY p.post_timestamp ASC, p.id ASC
            LIMIT ?, ?', $this->threadID, (($this->threadPage - 1) * $this->maximumResults), $this->maximumResults)->FetchAll();

        if (count($posts) <= 0)
            return;

        LikeController::EnsureSchema($this->database);

        $postIDs = array_map('intval', array_column($posts, 'id'));
        $userId = $this->canLike ? (int) $_SESSION['bitboard_user']->id : null;
        [$reactionCounts, $userReactions] = LikeController::GetPostReactions($this->database, $postIDs, $userId);

        $userIds = [];
        foreach ($posts as $post)
            $userIds[] = (int) $post['user_id'];

        $userStats = $this->fetchUserStats($userIds);

        $postTemplate = '';
        foreach ($posts as $post) 
        {
            $postUserStats = $userStats[(int) $post['user_id']] ?? ['thread_count' => 0, 'post_count' => 0];
            $userPostCount = $postUserStats['thread_count'] + $postUserStats['post_count'];

            $userStatsTemplate = new Template('thread', 'thread_user_stats');
            $userStatsTemplate->AddEscapedEntry('{user_reputation}', $post['reputation']);
            $userStatsTemplate->AddEscapedEntry('{user_postcount}', $userPostCount);
            $userStatsTemplate->Replace();

            $postTemplate = new Template('thread', 'post');
            $postTemplate->AddEscapedEntry('{post_id}', $post['id']);
            $postTemplate->AddEscapedEntry('{user_id}', $post['author_id']);
            $postTemplate->AddEntry('{user_name}', UsernameUtils::Format($post['rank_format'], $post['username']));
            $postTemplate->AddEntry('{user_avatar}', AvatarUtils::GetPath($post['avatar']));
            $postTemplate->AddEscapedEntry('{user_avatar_alt}', $post['username']);
            $postTemplate->AddEscapedEntry('{user_rank}', $post['rank_name']);
            $postTemplate->AddEntry('{user_stats}', $userStatsTemplate->template);
            $postTemplate->AddEscapedEntry('{post_timestamp}', 'Published ' . RelativeTime::Format($post['post_timestamp']));
            $postTemplate->AddEscapedEntry('{post_edited}', $this->EditedLabel($post['post_timestamp'], $post['post_edited_timestamp']));
            $postTemplate->AddEntry('{post_content}', MentionController::Link(
                $this->database,
                HtmlSanitizer::Sanitize($post['post_content'])
            ));

            $postQuote = '';
            if ($this->CanReply())
            {
                $postQuote = $this->BuildQuoteControls(
                    'post-' . $post['id'],
                    Text::Quote($post['post_content'], $post['username']),
                    (string) $post['id']
                );
            }
            $postTemplate->AddEntry('{post_quote}', $postQuote);
            $postTemplate->AddEntry('{post_report}', $this->BuildReportLink(
                'post',
                (int) $post['id'],
                $this->threadPage,
                (int) $post['user_id']
            ));

            $postSignature = '';
            $signatureText = trim((string) ($post['signature'] ?? ''));

            if ($signatureText !== '')
            {
                $signatureTemplate = new Template('thread', 'post_signature');
                $signatureTemplate->AddEscapedEntry('{signature}', $signatureText);
                $signatureTemplate->Replace();
                $postSignature = $signatureTemplate->template;
            }

            $postTemplate->AddEntry('{post_signature}', $postSignature);

            $postEdit = '';
            $canEditPost = ContentController::CanEditPost((int) $post['user_id']);
            $canDeletePost = ContentController::CanDeletePost((int) $post['user_id']);

            if ($canEditPost || $canDeletePost)
            {
                $editTemplate = new Template('thread', 'edit_link');
                $editTemplate->AddEscapedEntry('{edit_url}', $this->serverPath . 'edit?type=post&id=' . $post['id']);
                $editTemplate->AddEscapedEntry('{label}', $canEditPost ? 'Edit' : 'Delete');
                $editTemplate->AddEscapedEntry('{icon}', $canEditPost ? 'ti-pencil' : 'ti-trash');
                $editTemplate->Replace();
                $postEdit = $editTemplate->template;
            }
            $postTemplate->AddEntry('{post_edit}', $postEdit);

            $reactionWidget = new ReactionWidget(
                'post',
                (int) $post['id'],
                $this->threadPage,
                (int) $post['post_likes'],
                $reactionCounts[(int) $post['id']] ?? [],
                $userReactions[(int) $post['id']] ?? null,
                $this->canLike
            );
            $postTemplate->AddEntry('{post_like}', $reactionWidget->Template);
            $postTemplate->Replace();

            $this->posts .= $postTemplate->template;
            $this->lastPostID = max($this->lastPostID, (int) $post['id']);
        }
    }

    /**
     * Build a report link for the thread or a reply.
     */
    private function BuildReportLink(string $type, int $id, int $page, int $authorID): string
    {
        if (!SessionManager::IsLogged()
            || !isset($_SESSION['bitboard_user']->id)
            || (int) $_SESSION['bitboard_user']->id === $authorID)
        {
            return '';
        }

        $template = new Template('thread', 'report_link');
        $template->AddEscapedEntry('{url}', $this->serverPath . 'report?type=' . $type . '&id=' . $id . '&page=' . $page);
        $template->Replace();

        return $template->template;
    }

    /**
     * Build the staff pin/lock controls for the thread opener.
     */
    private function BuildModeration(): string
    {
        $canPin = SessionManager::HasPermission(Permissions::PINNING_THREADS);
        $canLock = SessionManager::HasPermission(Permissions::LOCKING_THREADS);
        $canMove = $canPin || $canLock;

        if (!$canPin && !$canLock)
            return '';

        $actions = '';

        if ($canPin)
        {
            $actions .= $this->BuildModerationAction(
                $this->threadPinned ? 'unpin' : 'pin',
                $this->threadPinned ? 'Unpin' : 'Pin',
                'ti-pin'
            );
        }

        if ($canLock)
        {
            $actions .= $this->BuildModerationAction(
                $this->threadClosed ? 'unlock' : 'lock',
                $this->threadClosed ? 'Unlock' : 'Lock',
                $this->threadClosed ? 'ti-lock-open' : 'ti-lock'
            );
        }

        if ($canMove)
        {
            $actions .= '<a class="thread-moderation-button" href="'
                . $this->serverPath . 'move?thread=' . $this->threadID . '">'
                . '<i class="ti ti-arrows-move" aria-hidden="true"></i> Move</a>';
        }

        $status = '';

        if ($this->threadPinned)
            $status .= '<span class="thread-moderation-status is-pinned"><i class="ti ti-pin" aria-hidden="true"></i> Pinned</span>';

        if ($this->threadClosed)
            $status .= '<span class="thread-moderation-status is-locked"><i class="ti ti-lock" aria-hidden="true"></i> Locked</span>';

        $template = new Template('thread', 'moderation');
        $template->AddEntry('{status}', $status);
        $template->AddEntry('{actions}', $actions);
        $template->Replace();

        return $template->template;
    }

    private function BuildModerationAction(string $action, string $label, string $icon): string
    {
        $template = new Template('thread', 'moderation_action');
        $template->AddEscapedEntry('{action}', $action);
        $template->AddEscapedEntry('{label}', $label);
        $template->AddEscapedEntry('{icon}', $icon);
        $template->AddEscapedEntry('{thread_id}', $this->threadID);
        $template->AddEscapedEntry('{page}', $this->threadPage);
        $template->AddEscapedEntry('{csrf_token}', SessionManager::CsrfToken());
        $template->Replace();

        return $template->template;
    }

    private function EditedLabel(string $createdAt, string $editedAt): string
    {
        if (strtotime($editedAt) <= strtotime($createdAt))
            return '';

        return ' · Edited ' . RelativeTime::Convert($editedAt);
    }

    /**
     * Build the quote and multi-quote controls for the thread or a reply.
     */
    private function BuildQuoteControls(string $key, string $quoteText, string $target): string
    {
        $template = new Template('thread', 'quote_controls');
        $template->AddEscapedEntry('{quote_url}', $this->serverPath . 'post?thread=' . $this->threadID . '&quote=' . $target);
        $template->AddEscapedEntry('{quote_text}', $quoteText);
        $template->AddEscapedEntry('{quote_key}', $key);
        $template->Replace();

        return $template->template;
    }

    /**
     * Check whether the current user can reply to this thread.
     */
    private function CanReply(): bool
    {
        if (!SessionManager::HasPermission(Permissions::CREATING_POSTS))
            return false;

        if ($this->threadClosed && !SessionManager::IsStaff())
            return false;

        if ($this->threadLocked)
        {
            return SessionManager::HasPermission(Permissions::CREATING_POSTS_LOCKED)
                && SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED);
        }

        return true;
    }

    /**
     * Fetch thread/post counters for several users in one query.
     */
    private function fetchUserStats(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if (count($userIds) <= 0)
            return [];

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = $this->database->Query('SELECT a.id,
                (SELECT COUNT(*) FROM bit_threads AS t WHERE t.user_id = a.id) AS thread_count,
                (SELECT COUNT(*) FROM bit_posts AS p WHERE p.user_id = a.id) AS post_count
            FROM bit_accounts AS a
            WHERE a.id IN (' . $placeholders . ')', $userIds)->FetchAll();

        $stats = [];
        foreach ($rows as $row)
        {
            $stats[(int) $row['id']] = [
                'thread_count' => (int) $row['thread_count'],
                'post_count' => (int) $row['post_count'],
            ];
        }

        return $stats;
    }

    public function Do()
    {
        $this->fetchThread();
        $this->fetchPosts();

        if (SessionManager::IsLogged() && $this->lastPostID > 0)
        {
            ThreadReadController::MarkRead(
                $this->database,
                (int) $_SESSION['bitboard_user']->id,
                $this->threadID,
                $this->lastPostID
            );
        }

        $canReply = $this->CanReply();

        if ($this->threadClosed && !$canReply)
        {
            $closedTemplate = new Template('thread', 'closed_notice');
            $this->replyArea = $closedTemplate->template;
        }
        elseif ($canReply)
        {
            $replyTemplate = new Template('thread', 'fast_reply');
            $replyTemplate->AddEscapedEntry('{thread_id}', $this->threadID);
            $replyTemplate->AddEscapedEntry('{return_page}', $this->threadPage);
            $replyTemplate->AddEscapedEntry('{hint}', $this->threadClosed
                ? 'This thread is closed, but staff can still reply.'
                : 'Post a reply without leaving this thread.');
            $replyTemplate->Replace();
            $this->replyArea = $replyTemplate->template;
        }

        $paginationTemplate = new PaginationWidget($this->threadPage, $this->postsCount, $this->maximumResults, 'thread/' . $this->threadID . '/' . $this->threadSlug . '/page/');
    
        $this->template = new Template('thread', 'thread');
        $this->template->AddEscapedEntry('{thread_id}', $this->threadID);
        $this->template->AddEntry('{thread}', $this->thread);
        $this->template->AddEntry('{posts}', $this->posts);
        $this->template->AddEntry('{reply_area}', $this->replyArea);
        $this->template->AddEntry('{pagination}', $this->postsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');
        
        parent::RenderPage('thread');
    }
}

?>