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

use App\Forum\Widgets\PaginationWidget;

class ThreadPage extends PageBase implements PageInterface
{
    public const POSTS_PER_PAGE = 7;

    private string $thread = '';
    private string $posts = '';
    private string $replyArea = '';

    private int $threadID;
    private int $threadPage;
    private string $threadSlug = '';
    private bool $threadClosed = false;
    private bool $threadLocked = false;

    private int $maximumResults = self::POSTS_PER_PAGE;
    private int $postsCount = 0;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
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

        $thread = $this->database->Query('SELECT t.id, t.thread_title, t.thread_content, t.is_closed,
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
        $threadContentTemplate->AddEscapedEntry('{thread_title}', $thread['thread_title']);
        $threadContentTemplate->AddEscapedEntry('{thread_timestamp}', 'Published ' . RelativeTime::Format($thread['thread_timestamp']));
        $threadContentTemplate->AddEntry('{thread_content}', HtmlSanitizer::Sanitize($thread['thread_content']));
        $threadContentTemplate->AddEscapedEntry('{thread_likes}', $thread['thread_likes'] > 0 ? $thread['thread_likes'] : '');
        // TODO: Add like button or new like widgets for posts if user is logged!

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
        $posts = $this->database->Query('SELECT p.*, a.id AS author_id, a.username, a.avatar, a.reputation, r.rank_name, r.rank_format
            FROM bit_posts AS p
            JOIN bit_accounts AS a ON p.user_id = a.id
            JOIN bit_ranks AS r ON a.rank_id = r.id
            WHERE p.thread_id = ?
            ORDER BY p.post_timestamp ASC, p.id ASC
            LIMIT ?, ?', $this->threadID, (($this->threadPage - 1) * $this->maximumResults), $this->maximumResults)->FetchAll();

        if (count($posts) <= 0)
            return;

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
            $postTemplate->AddEscapedEntry('{user_id}', $post['author_id']);
            $postTemplate->AddEntry('{user_name}', UsernameUtils::Format($post['rank_format'], $post['username']));
            $postTemplate->AddEntry('{user_avatar}', AvatarUtils::GetPath($post['avatar']));
            $postTemplate->AddEscapedEntry('{user_avatar_alt}', $post['username']);
            $postTemplate->AddEscapedEntry('{user_rank}', $post['rank_name']);
            $postTemplate->AddEntry('{user_stats}', $userStatsTemplate->template);
            $postTemplate->AddEscapedEntry('{post_timestamp}', 'Published ' . RelativeTime::Format($post['post_timestamp']));
            $postTemplate->AddEntry('{post_content}', HtmlSanitizer::Sanitize($post['post_content']));
            $postTemplate->AddEscapedEntry('{post_likes}', $post['post_likes'] > 0 ? $post['post_likes'] : '');
            $postTemplate->Replace();

            $this->posts .= $postTemplate->template;
        }
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

        $canReply = !$this->threadClosed && SessionManager::HasPermission(Permissions::CREATING_POSTS);

        if ($canReply && $this->threadLocked)
        {
            $canReply = SessionManager::HasPermission(Permissions::CREATING_POSTS_LOCKED)
                && SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED);
        }

        if ($this->threadClosed)
        {
            $closedTemplate = new Template('thread', 'closed_notice');
            $this->replyArea = $closedTemplate->template;
        }
        elseif ($canReply)
        {
            $replyTemplate = new Template('thread', 'fast_reply');
            $replyTemplate->AddEscapedEntry('{thread_id}', $this->threadID);
            $replyTemplate->AddEscapedEntry('{return_page}', $this->threadPage);
            $replyTemplate->Replace();
            $this->replyArea = $replyTemplate->template;
        }

        $paginationTemplate = new PaginationWidget($this->threadPage, $this->postsCount, $this->maximumResults, 'thread/' . $this->threadID . '/' . $this->threadSlug . '/page/');
    
        $this->template = new Template('thread', 'thread');
        $this->template->AddEntry('{thread}', $this->thread);
        $this->template->AddEntry('{posts}', $this->posts);
        $this->template->AddEntry('{reply_area}', $this->replyArea);
        $this->template->AddEntry('{pagination}', $this->postsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');
        
        parent::RenderPage('thread');
    }
}

?>