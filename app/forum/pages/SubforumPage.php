<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\UrlManager;
use App\Classes\Template;
use App\Forum\Widgets\PaginationWidget;
use App\Interfaces\PageInterface;

use App\Forum\Controllers\ThreadController;

use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\UsernameUtils;

class SubforumPage extends PageBase implements PageInterface
{
    private string $threads = '';

    private int $subforumPage;
    private int $subforumID;

    private int $maximumResults = 5;
    private int $threadsCount = 0;

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
            !is_numeric($this->forumData->actionParameters[1]) || // Check if the second segment is a numeric forum ID
            !is_numeric($this->forumData->actionParameters[3]) // Check if the fourth segment is a numeric page number
        ) {
            UrlManager::Redirect($this->serverPath . 'subforum/1/page/1');
            return;
        }

        $this->subforumPage = $this->forumData->actionParameters[3];
        $this->subforumID = $this->forumData->actionParameters[1];

        if ($this->subforumPage <= 0 || $this->subforumID <= 0)
        {
            UrlManager::Redirect($this->serverPath . 'subforum/1/page/1');
            return;
        }

        $subforumID = $this->database->Query('SELECT id FROM bit_subforums WHERE id = ?', $this->subforumID)->FetchArray();
        if (empty($subforumID))
        {
            UrlManager::Redirect($this->serverPath . 'subforum/1/page/1');
            return;
        }
    }

    private function FetchThreads()
    {
        $this->threadsCount = $this->database->Query('SELECT id FROM bit_threads WHERE subforum_id = ?', $this->subforumID)->NumRows();
       
        $totalPages = ceil($this->threadsCount / $this->maximumResults);

        if ($this->subforumPage > $totalPages && $this->threadsCount > 0)
        {
            UrlManager::Redirect($this->serverPath . 'forum/' . $this->subforumID . '/page/' . $totalPages);
            return;
        }
       
        $threads = $this->database->Query('SELECT bit_threads.*, bit_accounts.id AS author_id, bit_accounts.username, bit_accounts.rank_id, bit_ranks.rank_format, 
            COUNT(bit_posts.id) AS post_count, MAX(bit_posts.post_timestamp) AS latest_post_timestamp
            FROM bit_threads 
            LEFT JOIN bit_posts ON bit_threads.id = bit_posts.thread_id 
            LEFT JOIN bit_accounts ON bit_threads.user_id = bit_accounts.id 
            LEFT JOIN bit_ranks ON bit_accounts.rank_id = bit_ranks.id 
            WHERE subforum_id = ?
            GROUP BY bit_threads.id 
            ORDER BY bit_threads.is_pinned DESC, latest_post_timestamp DESC, bit_threads.thread_timestamp DESC
            LIMIT ?, ?',
            [$this->subforumID, ($this->subforumPage - 1) * $this->maximumResults, $this->maximumResults]
        )->FetchAll();

        if ($this->threadsCount <= 0)
        {
            $nothreadsTemplate = new Template('subforum/thread', 'thread_nothreads');
            $this->threads = $nothreadsTemplate->template;
            return;
        }

        foreach ($threads as $thread) 
        {
            $lastPostTemplate = new Template('subforum/thread', 'thread_nolastpost');
            $threadClosedTemplate = '';
            $threadPinnedTemplate = '';

            if ($thread['post_count'] > 0) {
                $lastPost = ThreadController::GetLastPost($this->database, $thread['id']);

                $lastPostTemplate = new Template('subforum/thread', 'thread_lastpost');
                $lastPostTemplate->AddEntry('{user_id}', $lastPost['id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                $lastPostTemplate->Replace();
            }

            if ($thread['is_closed'])
            {
                $threadClosedTemplate = new Template('forum/thread', 'thread_closed_prefix');
                $threadClosedTemplate = $threadClosedTemplate->template;
            }

            if ($thread['is_pinned'])
            {
                $threadPinnedTemplate = new Template('forum/thread', 'thread_pinned_prefix');
                $threadPinnedTemplate = $threadPinnedTemplate->template;
            }

            $threadTemplate = new Template('forum/thread', 'thread');
            $threadTemplate->AddEntry('{id}', $thread['id']);
            $threadTemplate->AddEntry('{closed_prefix}', $threadClosedTemplate);
            $threadTemplate->AddEntry('{pinned_prefix}', $threadPinnedTemplate);
            $threadTemplate->AddEntry('{thread_title}', $thread['thread_title']);
            $threadTemplate->AddEntry('{thread_author_id}', $thread['author_id']);
            $threadTemplate->AddEntry('{thread_author_username}', UsernameUtils::Format($thread['rank_format'], $thread['username']));
            $threadTemplate->AddEntry('{thread_date}', RelativeTime::Convert($thread['thread_timestamp']));
            $threadTemplate->AddEntry('{thread_replies}', $thread['post_count']);
            $threadTemplate->AddEntry('{thread_lastpost}', $lastPostTemplate->template);
            $threadTemplate->Replace();

            $this->threads .= $threadTemplate->template;
        }
    }

    private function SetupForumDesc()
    {
        $subForumName = $this->database->Query('SELECT subforum_name FROM bit_subforums WHERE id = ?', $this->subforumID)->FetchArray();
        $this->forumDesc = 'Subforum ' . $subForumName['subforum_name'];
    }

    public function Do()
    {
        $this->SetupForumDesc();
        $this->FetchThreads();

        $paginationTemplate = new PaginationWidget($this->subforumPage, $this->threadsCount, $this->maximumResults, 'forum/' . $this->subforumID . '/page/');

        $this->template = new Template('subforum', 'subforum');
        $this->template->AddEntry('{threads}', $this->threads);
        $this->template->AddEntry('{pagination}', $this->threadsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');

        parent::RenderPage('subforum');
    }
}

?>