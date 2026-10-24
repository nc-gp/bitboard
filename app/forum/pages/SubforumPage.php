<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\PageNotFoundException;
use App\Classes\UrlManager;
use App\Classes\Template;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Widgets\PaginationWidget;
use App\Interfaces\PageInterface;

use App\Forum\Controllers\ThreadController;

use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\UsernameUtils;
use App\Forum\Widgets\PrefixWidget;

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
        )
            throw new PageNotFoundException();

        $this->subforumPage = (int) $this->forumData->actionParameters[3];
        $this->subforumID = (int) $this->forumData->actionParameters[1];

        if ($this->subforumPage <= 0 || $this->subforumID <= 0)
            throw new PageNotFoundException();

        $subforum = $this->database->Query('SELECT sf.id, sf.forum_id, sf.is_locked, f.is_locked AS forum_locked
            FROM bit_subforums AS sf
            JOIN bit_forums AS f ON f.id = sf.forum_id
            WHERE sf.id = ?', $this->subforumID)->FetchArray();
        if (empty($subforum))
            throw new PageNotFoundException();

        $isLocked = (int) $subforum['is_locked'] === 1 || (int) $subforum['forum_locked'] === 1;
        if ($isLocked && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);
    }

    private function FetchThreads()
    {
        $this->threadsCount = $this->database->Query('SELECT id FROM bit_threads WHERE subforum_id = ?', $this->subforumID)->NumRows();
       
        $totalPages = ceil($this->threadsCount / $this->maximumResults);

        if ($this->subforumPage > $totalPages && $this->threadsCount > 0)
            UrlManager::Redirect($this->serverPath . 'subforum/' . $this->subforumID . '/page/' . $totalPages);
       
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

        $threadPrefix = '';
        foreach ($threads as $thread) 
        {
            $lastPostTemplate = new Template('subforum/thread', 'thread_nolastpost');
            $threadPrefix = '';

            if ($thread['post_count'] > 0) {
                $lastPost = ThreadController::GetLastPost($this->database, $thread['id']);

                $lastPostTemplate = new Template('subforum/thread', 'thread_lastpost');
                $lastPostTemplate->AddEscapedEntry('{user_id}', $lastPost['id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEscapedEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                $lastPostTemplate->Replace();
            }

            $threadTemplate = new Template('forum/thread', 'thread');

            if ($thread['is_closed'])
            {
                $threadPrefix = new PrefixWidget($this->database, 1);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{closed_prefix}', $threadPrefix);
            $threadPrefix = '';

            if ($thread['is_pinned'])
            {
                $threadPrefix = new PrefixWidget($this->database, 2);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{pinned_prefix}', $threadPrefix);
            $threadPrefix = '';

            if ($thread['prefix_id'] > 2)
            {
                $threadPrefix = new PrefixWidget($this->database, $thread['prefix_id']);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{custom_prefix}', $threadPrefix);
            $threadPrefix = '';

            $threadTemplate->AddEscapedEntry('{id}', $thread['id']);
            $threadTemplate->AddEscapedEntry('{thread_title}', $thread['thread_title']);
            $threadTemplate->AddEscapedEntry('{thread_author_id}', $thread['author_id']);
            $threadTemplate->AddEntry('{thread_author_username}', UsernameUtils::Format($thread['rank_format'], $thread['username']));
            $threadTemplate->AddEscapedEntry('{thread_date}', RelativeTime::Convert($thread['thread_timestamp']));
            $threadTemplate->AddEscapedEntry('{thread_replies}', $thread['post_count']);
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

        $paginationTemplate = new PaginationWidget($this->subforumPage, $this->threadsCount, $this->maximumResults, 'subforum/' . $this->subforumID . '/page/');

        $this->template = new Template('subforum', 'subforum');
        $this->template->AddEntry('{threads}', $this->threads);
        $this->template->AddEntry('{pagination}', $this->threadsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');

        parent::RenderPage('subforum');
    }
}

?>