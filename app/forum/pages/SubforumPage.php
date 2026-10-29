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
use App\Forum\Controllers\ContentController;
use App\Forum\Controllers\PostController;
use App\Forum\Controllers\ThreadReadController;
use App\Forum\Widgets\WatchWidget;

use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\Slug;
use App\Classes\UsernameUtils;
use App\Forum\Widgets\PrefixWidget;

class SubforumPage extends PageBase implements PageInterface
{
    private string $threads = '';

    private int $subforumPage;
    private int $subforumID;

    private int $maximumResults = 5;
    private int $threadsCount = 0;
    private int $parentForumID = 0;
    private bool $forumLocked = false;
    private bool $subforumLocked = false;

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

        $this->parentForumID = (int) $subforum['forum_id'];
        $this->subforumLocked = (int) $subforum['is_locked'] === 1;
        $this->forumLocked = (int) $subforum['forum_locked'] === 1;

        if (($this->subforumLocked || $this->forumLocked) && !SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
            UrlManager::Redirect($this->serverPath);
    }

    private function FetchThreads()
    {
        $this->threadsCount = ThreadController::CountThreads($this->database, 0, $this->subforumID);
       
        $totalPages = ceil($this->threadsCount / $this->maximumResults);

        if ($this->subforumPage > $totalPages && $this->threadsCount > 0)
            UrlManager::Redirect($this->serverPath . 'subforum/' . $this->subforumID . '/page/' . $totalPages);
       
        $threads = ThreadController::GetThreads(
            $this->database,
            0,
            $this->subforumID,
            ($this->subforumPage - 1) * $this->maximumResults,
            $this->maximumResults
        );

        if ($this->threadsCount <= 0)
        {
            $nothreadsTemplate = new Template('subforum/thread', 'thread_nothreads');
            $this->threads = $nothreadsTemplate->template;
            return;
        }

        $lastPosts = ThreadController::GetLastPosts($this->database, array_column($threads, 'id'));

        $readData = [];

        if (SessionManager::IsLogged())
        {
            $readData = ThreadReadController::GetReadData(
                $this->database,
                (int) $_SESSION['bitboard_user']->id,
                array_column($threads, 'id')
            );
        }

        $threadPrefix = '';
        foreach ($threads as $thread) 
        {
            $lastPostTemplate = new Template('subforum/thread', 'thread_nolastpost');
            $lastPost = $lastPosts[(int) $thread['id']] ?? [];
            $threadPrefix = '';

            if (!empty($lastPost)) {
                $lastPostTemplate = new Template('subforum/thread', 'thread_lastpost');
                $lastPostTemplate->AddEscapedEntry('{user_id}', $lastPost['user_id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEscapedEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));

                $lastPostPage = ContentController::PostPageNumber($this->database, [
                    'thread_id' => (int) $thread['id'],
                    'post_timestamp' => $lastPost['post_timestamp'],
                    'id' => (int) $lastPost['id'],
                ]);

                $lastPostTemplate->AddEntry('{last_post_url}', UrlManager::ThreadUrl(
                    (int) $thread['id'],
                    Slug::FromTitle($thread['thread_title']),
                    $lastPostPage
                ) . '#post-' . (int) $lastPost['id']);

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
            $threadTemplate->AddEntry('{thread_url}', UrlManager::ThreadUrl(
                (int) $thread['id'],
                Slug::FromTitle($thread['thread_title'])
            ));
            $threadTemplate->AddEscapedEntry('{thread_author_id}', $thread['author_id']);
            $threadTemplate->AddEntry('{thread_author_username}', UsernameUtils::Format($thread['rank_format'], $thread['username']));
            $threadTemplate->AddEscapedEntry('{thread_date}', RelativeTime::Convert($thread['thread_timestamp']));
            $threadTemplate->AddEscapedEntry('{thread_replies}', $thread['post_count']);
            $threadTemplate->AddEntry('{unread_link}', $this->BuildUnreadLink($thread, $readData));
            $threadTemplate->AddEntry('{thread_lastpost}', $lastPostTemplate->template);
            $threadTemplate->Replace();

            $this->threads .= $threadTemplate->template;
        }
    }

    private function BuildUnreadLink(array $thread, array $readData): string
    {
        $postCount = (int) $thread['post_count'];
        $read = $readData[(int) $thread['id']] ?? null;

        if (empty($read) || (int) $read['read_count'] <= 0 || (int) $read['read_count'] >= $postCount)
            return '';

        $page = max(1, (int) ceil(((int) $read['read_count'] + 1) / PostController::POSTS_PER_PAGE));
        $url = UrlManager::ThreadUrl(
            (int) $thread['id'],
            Slug::FromTitle($thread['thread_title']),
            $page
        );

        if ((int) $read['first_unread_id'] > 0)
            $url .= '#post-' . (int) $read['first_unread_id'];

        $template = new Template('forum/thread', 'thread_unread');
        $template->AddEscapedEntry('{url}', $url);
        $template->Replace();

        return $template->template;
    }

    private function SetupForumDesc()
    {
        $subforum = $this->database->Query('SELECT subforum_name, subforum_desc FROM bit_subforums WHERE id = ?', $this->subforumID)->FetchArray();
        $this->forumDesc = 'Subforum ' . $subforum['subforum_name'];
        $this->canonicalUrl = $this->serverPath . 'subforum/' . $this->subforumID . '/page/' . $this->subforumPage;
        $this->ogTitle = $subforum['subforum_name'] . ' - ' . $this->forumName;
        $this->ogDescription = $subforum['subforum_desc'];
    }

    public function Do()
    {
        $this->SetupForumDesc();
        $this->FetchThreads();

        $paginationTemplate = new PaginationWidget($this->subforumPage, $this->threadsCount, $this->maximumResults, 'subforum/' . $this->subforumID . '/page/');

        $newThreadButton = '';
        $canCreateThread = SessionManager::HasPermission(Permissions::CREATING_THREADS);

        if ($canCreateThread && ($this->subforumLocked || $this->forumLocked))
        {
            $canCreateThread = SessionManager::HasPermission(Permissions::CREATING_THREADS_LOCKED)
                && SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED);
        }

        if ($canCreateThread)
        {
            $buttonTemplate = new Template('forum', 'new_thread_button');
            $buttonTemplate->AddEscapedEntry('{new_thread_url}', $this->serverPath . 'post?forum=' . $this->parentForumID . '&subforum=' . $this->subforumID);
            $buttonTemplate->Replace();
            $newThreadButton = $buttonTemplate->template;
        }

        $this->template = new Template('subforum', 'subforum');
        $this->template->AddEntry('{watch_button}', (new WatchWidget(
            $this->database,
            (int) $this->parentForumID,
            (int) $this->subforumID,
            (int) $this->subforumPage,
            'subforum'
        ))->template);
        $this->template->AddEntry('{new_thread_button}', $newThreadButton);
        $this->template->AddEntry('{threads}', $this->threads);
        $this->template->AddEntry('{pagination}', $this->threadsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');

        parent::RenderPage('subforum');
    }
}

?>