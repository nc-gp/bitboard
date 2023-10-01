<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;
use App\Classes\Console;

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
        ) {
            $threadID = is_numeric($this->forumData->actionParameters[1]) ? $this->forumData->actionParameters[1] : 1;

            UrlManager::Redirect($this->serverPath . 'thread/' . $threadID . '/page/1');
        }

        $this->threadPage = $this->forumData->actionParameters[3];
        $this->threadID = $this->forumData->actionParameters[1];

        if ($this->threadPage <= 0 || $this->threadID <= 0)
            UrlManager::Redirect($this->serverPath . 'thread/1/page/1');

        $threadID = $this->database->Query('SELECT id FROM bit_threads WHERE id = ?', $this->threadID)->FetchArray();
        if (empty($threadID))
            UrlManager::Redirect($this->serverPath . 'thread/1/page/1');
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

        // i guess we need one query of that, or just add another label for the user in database.
        $threadCount = $this->database->Query('SELECT COUNT(*) AS thread_count FROM bit_threads WHERE user_id = ?', $thread['user_id'])->FetchArray();
        $postCount = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE user_id = ?', $thread['user_id'])->FetchArray();
        $userPostCount = $threadCount['thread_count'] + $postCount['post_count'];

        Console::Log($thread);

        $userStatsTemplate = new Template('thread', 'thread_user_stats');
        $userStatsTemplate->AddEntry('{user_reputation}', $thread['user_reputation']);
        $userStatsTemplate->AddEntry('{user_postcount}', $userPostCount);
        $userStatsTemplate->Replace();

        $threadContentTemplate = new Template('thread', 'thread_content');
        $threadContentTemplate->AddEntry('{thread_title}', $thread['thread_title']);
        $threadContentTemplate->AddEntry('{thread_timestamp}', 'Published ' . RelativeTime::Format($thread['thread_timestamp']));
        $threadContentTemplate->AddEntry('{thread_content}', $thread['thread_content']);
        $threadContentTemplate->AddEntry('{thread_likes}', $thread['thread_likes'] > 0 ? $thread['thread_likes'] : '');
        // TODO: Add like button or new like widgets for posts if user is logged!

        $threadContentTemplate->AddEntry('{user_id}', $thread['user_id']);
        $threadContentTemplate->AddEntry('{user_name}', UsernameUtils::Format($thread['rank_format'], $thread['user_name']));
        $threadContentTemplate->AddEntry('{user_avatar}', AvatarUtils::GetPath($thread['user_avatar']));
        $threadContentTemplate->AddEntry('{user_avatar_alt}', $thread['user_name']);
        $threadContentTemplate->AddEntry('{user_rank}', $thread['rank_name']);
        $threadContentTemplate->AddEntry('{user_stats}', $userStatsTemplate->template);
        $threadContentTemplate->Replace();

        $this->thread = $threadContentTemplate->template;

        $this->forumDesc .= $thread['thread_title'];
    }

    private function fetchPosts()
    {
        $posts = $this->database->Query('SELECT * FROM bit_posts WHERE thread_id = ? LIMIT ?, ?', $this->threadID, (($this->threadPage - 1) * $this->maximumResults), $this->maximumResults)->FetchAll();

        $postTemplate = '';
        foreach ($posts as $post) 
        {
            $author = $this->database->Query('SELECT b.*, r.* FROM bit_accounts AS b JOIN bit_ranks AS r ON b.rank_id = r.id WHERE b.id = ?', $post['user_id'])->FetchArray();

            $threadCount = $this->database->Query('SELECT COUNT(*) AS thread_count FROM bit_threads WHERE user_id = ?', $post['user_id'])->FetchArray();
            $postCount = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE user_id = ?', $post['user_id'])->FetchArray();
            $userPostCount = $threadCount['thread_count'] + $postCount['post_count'];

            $userStatsTemplate = new Template('thread', 'thread_user_stats');
            $userStatsTemplate->AddEntry('{user_reputation}', $author['reputation']);
            $userStatsTemplate->AddEntry('{user_postcount}', $userPostCount);
            $userStatsTemplate->Replace();

            $postTemplate = new Template('thread', 'post');
            $postTemplate->AddEntry('{user_id}', $author['id']);
            $postTemplate->AddEntry('{user_name}', UsernameUtils::Format($author['rank_format'], $author['username']));
            $postTemplate->AddEntry('{user_avatar}', AvatarUtils::GetPath($author['avatar']));
            $postTemplate->AddEntry('{user_avatar_alt}', $author['username']);
            $postTemplate->AddEntry('{user_rank}', $author['rank_name']);
            $postTemplate->AddEntry('{user_stats}', $userStatsTemplate->template);
            $postTemplate->AddEntry('{post_timestamp}', 'Published ' . RelativeTime::Format($post['post_timestamp']));
            $postTemplate->AddEntry('{post_content}', $post['post_content']);
            $postTemplate->AddEntry('{post_likes}', $post['post_likes'] > 0 ? $post['post_likes'] : '');
            $postTemplate->Replace();

            $this->posts .= $postTemplate->template;
        }
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