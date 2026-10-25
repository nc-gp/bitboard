<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\PageNotFoundException;
use App\Classes\Template;
use App\Classes\Avatar;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;
use App\Classes\Permissions;
use App\Classes\SessionManager;

use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

use App\Forum\Controllers\ThreadController;
use App\Forum\Controllers\SubForumController;
use App\Forum\Widgets\PrefixWidget;

class ForumPage extends PageBase implements PageInterface
{
    private string $subforums = '';
    private string $threads = '';

    private int $forumPage;
    private int $forumID;

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

        $this->forumPage = $this->forumData->actionParameters[3];
        $this->forumID = $this->forumData->actionParameters[1];

        if ($this->forumPage <= 0 || $this->forumID <= 0)
            throw new PageNotFoundException();

        $forum = $this->database->Query('SELECT id, is_locked FROM bit_forums WHERE id = ?', $this->forumID)->FetchArray();
        if (empty($forum))
            throw new PageNotFoundException();

        if ((int) $forum['is_locked'] === 1 && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);
    }

    private function FetchSubForums()
    {
        $subforums = $this->database->Query('SELECT sf.id, sf.subforum_name, sf.subforum_desc, 
            COUNT(DISTINCT p.id) AS post_count,
            COUNT(DISTINCT t.id) AS thread_count
            FROM bit_subforums AS sf
            LEFT JOIN bit_threads AS t ON t.subforum_id = sf.id
            LEFT JOIN bit_posts AS p ON p.thread_id = t.id
            WHERE sf.forum_id = ?
            GROUP BY sf.id
            ORDER BY sf.id ASC',
            [$this->forumID]
        )->FetchAll();

        if (count($subforums) <= 0)
            return;

        $lastPosts = SubForumController::GetLastPosts($this->database, $this->forumID, array_column($subforums, 'id'));

        foreach ($subforums as $subforum)
        {
            $lastPost = $lastPosts[(int) $subforum['id']] ?? [];

            $lastPostTemplate = new Template('forum/subforum', 'subforum_nolastpost');
            if(!empty($lastPost))
            {
                $lastPostTemplate = new Template('forum/subforum', 'subforum_lastpost');
                $lastPostTemplate->AddEscapedEntry('{user_id}', $lastPost['id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEscapedEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                $lastPostTemplate->Replace();
            }

            $subforumTemplate = new Template('forum/subforum', 'subforum');
            $subforumTemplate->AddEscapedEntry('{id}', $subforum['id']);
            $subforumTemplate->AddEscapedEntry('{subforum_title}', $subforum['subforum_name']);
            $subforumTemplate->AddEscapedEntry('{subforum_desc}', $subforum['subforum_desc']);
            $subforumTemplate->AddEscapedEntry('{post_count}', $subforum['post_count']);
            $subforumTemplate->AddEscapedEntry('{thread_count}', $subforum['thread_count']);
            $subforumTemplate->AddEntry('{subforum_lastpost}', $lastPostTemplate->template);
            $subforumTemplate->Replace();

            $this->subforums .= $subforumTemplate->template;
        }
    }

    private function FetchThreads()
    {
        $this->threadsCount = ThreadController::CountThreads($this->database, $this->forumID, -1);
       
        $totalPages = ceil($this->threadsCount / $this->maximumResults);

        if ($this->forumPage > $totalPages && $this->threadsCount > 0)
        {
            UrlManager::Redirect($this->serverPath . 'forum/' . $this->forumID . '/page/' . $totalPages);
            return;
        }
       
        $threads = ThreadController::GetThreads(
            $this->database,
            $this->forumID,
            -1,
            ($this->forumPage - 1) * $this->maximumResults,
            $this->maximumResults
        );

        if ($this->threadsCount <= 0)
        {
            $nothreadsTemplate = new Template('forum/thread', 'thread_nothreads');
            $this->threads = $nothreadsTemplate->template;
            return;
        }

        $lastPosts = ThreadController::GetLastPosts($this->database, array_column($threads, 'id'));

        $threadPrefix = '';
        foreach ($threads as $thread) 
        {
            $lastPostTemplate = new Template('forum/thread', 'thread_nolastpost');
            $lastPost = $lastPosts[(int) $thread['id']] ?? [];
            $threadPrefix = '';

            if (!empty($lastPost)) {
                $lastPostTemplate = new Template('forum/thread', 'thread_lastpost');
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
        $forumName = $this->database->Query('SELECT forum_name FROM bit_forums WHERE id = ?', $this->forumID)->FetchArray();
        $this->forumDesc = 'Forum ' . $forumName['forum_name'];
    }

    public function Do()
    {
        $this->SetupForumDesc();
        $this->FetchSubForums();
        $this->FetchThreads();

        $paginationTemplate = new PaginationWidget($this->forumPage, $this->threadsCount, $this->maximumResults, 'forum/' . $this->forumID . '/page/');

        $this->template = new Template('forum', 'forum');
        $this->template->AddEntry('{subforums}', $this->subforums);
        $this->template->AddEntry('{threads}', $this->threads);
        $this->template->AddEntry('{pagination}', $this->threadsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');
        
        parent::RenderPage('forum');
    }
}

?>