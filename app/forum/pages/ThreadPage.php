<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Template;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;
use App\Classes\HtmlSanitizer;
use App\Classes\Permissions;
use App\Classes\SessionManager;

use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

class ThreadPage extends PageBase implements PageInterface
{
    private string $thread = '';
    private string $posts = '';

    private int $threadID;
    private int $threadPage;

    private int $maximumResults = 7;
    private int $postsCount = 0;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if (
            (count($this->forumData->actionParameters) < 4) || // Check if the URL has enough segments
            ($this->forumData->actionParameters[2] !== 'page') || // Check if the segment after 'forum' is 'page'
            !is_numeric($this->forumData->actionParameters[1]) || // Check if the second segment is a numeric thread ID
            !is_numeric($this->forumData->actionParameters[3]) // Check if the fourth segment is a numeric page number
        )
            throw new PageNotFoundException();

        $this->threadPage = (int) $this->forumData->actionParameters[3];
        $this->threadID = (int) $this->forumData->actionParameters[1];

        if ($this->threadPage <= 0 || $this->threadID <= 0)
            throw new PageNotFoundException();

        $thread = $this->database->Query('SELECT t.id, f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE t.id = ?', $this->threadID)->FetchArray();
        if (empty($thread))
            throw new PageNotFoundException();

        $isLocked = (int) $thread['forum_locked'] === 1 || (int) ($thread['subforum_locked'] ?? 0) === 1;
        if ($isLocked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);

        $postCount = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE thread_id = ?', $this->threadID)->FetchArray();
        $this->postsCount = (int) ($postCount['post_count'] ?? 0);

        $totalPages = max(1, (int) ceil($this->postsCount / $this->maximumResults));
        if ($this->threadPage > $totalPages)
            UrlManager::Redirect($this->serverPath . 'thread/' . $this->threadID . '/page/' . $totalPages);
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

        $this->forumDesc .= $thread['thread_title'];
    }

    private function fetchPosts()
    {
        $posts = $this->database->Query('SELECT p.*, a.id AS author_id, a.username, a.avatar, a.reputation, r.rank_name, r.rank_format
            FROM bit_posts AS p
            JOIN bit_accounts AS a ON p.user_id = a.id
            JOIN bit_ranks AS r ON a.rank_id = r.id
            WHERE p.thread_id = ?
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

        $paginationTemplate = new PaginationWidget($this->threadPage, $this->postsCount, $this->maximumResults, 'thread/' . $this->threadID . '/page/');
    
        $this->template = new Template('thread', 'thread');
        $this->template->AddEntry('{thread}', $this->thread);
        $this->template->AddEntry('{posts}', $this->posts);
        $this->template->AddEntry('{pagination}', $this->postsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');
        
        parent::RenderPage('thread');
    }
}

?>