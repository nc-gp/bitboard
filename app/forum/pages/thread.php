<?php

require_once './app/helpers/avatar.php';
require_once './app/helpers/username.php';
require_once './app/helpers/time.php';

class ThreadPage
{
    private $template;
    private string $theme;
    private string $forumName;
    private string $forumDesc;
    private $database;

    private string $thread = '';
    private string $posts = '';

    private string $serverPath;

    private int $threadID;
    private int $threadPage;

    private int $maximumResults = 7;
    private int $postsCount = 0;

    public function __construct($db, array $forumData)
    {
        $this->theme = $forumData['forum_theme'];
        $this->forumName = $forumData['forum_name'];
        $this->forumDesc = 'Thread ';
        $this->database = $db;
        $this->UrlHandler();
        $this->Do();
    }

    private function UrlHandler()
    {
        $url = explode('/', $_GET['action']);

        $this->serverPath = UrlManager::GetPath();

        if (
            (count($url) < 4) || // Check if the URL has enough segments
            ($url[2] !== 'page') || // Check if the segment after 'forum' is 'page'
            !is_numeric($url[1]) || // Check if the second segment is a numeric thread ID
            !is_numeric($url[3]) // Check if the fourth segment is a numeric page number
        ) {
            UrlManager::Redirect($this->serverPath . 'thread/1/page/1');
            return;
        }

        $this->threadPage = $url[3];
        $this->threadID = $url[1];

        if ($this->threadPage <= 0 || $this->threadID <= 0)
        {
            UrlManager::Redirect($this->serverPath . 'thread/1/page/1');
            return;
        }

        $threadID = $this->database->Query('SELECT id FROM bit_threads WHERE id = ?', $this->threadID)->FetchArray();
        if (empty($threadID))
        {
            UrlManager::Redirect($this->serverPath . 'thread/1/page/1');
            return;
        }
    }

    private function fetchThread()
    {
        $thread = $this->database->Query('SELECT t.*, u.username AS user_name, u.avatar AS user_avatar, u.reputation AS user_reputation, u.last_active AS user_lastactive, r.id AS rank_id, r.rank_name, r.rank_format
                                    FROM bit_threads t
                                    JOIN bit_accounts u ON t.user_id = u.id
                                    JOIN bit_ranks r ON u.rank_id = r.id
                                    WHERE t.id = ?', $this->threadID)->FetchArray();

        // i guess we need one query of that, or just add another label for the user in database.
        $threadCount = $this->database->Query('SELECT COUNT(*) AS thread_count FROM bit_threads WHERE user_id = ?', $thread['user_id'])->FetchArray();
        $postCount = $this->database->Query('SELECT COUNT(*) AS post_count FROM bit_posts WHERE user_id = ?', $thread['user_id'])->FetchArray();
        $userPostCount = $threadCount['thread_count'] + $postCount['post_count'];

        Log::Log($thread);

        $userStatsTemplate = new Template('./themes/' . $this->theme . '/templates/thread/thread_user_stats.html');
        $userStatsTemplate->AddEntry('{user_reputation}', $thread['user_reputation']);
        $userStatsTemplate->AddEntry('{user_postcount}', $userPostCount);
        $userStatsTemplate->Replace();

        $threadContentTemplate = new Template('./themes/' . $this->theme . '/templates/thread/thread_content.html');
        $threadContentTemplate->AddEntry('{thread_title}', $thread['thread_title']);
        $threadContentTemplate->AddEntry('{thread_timestamp}', RelativeTime::Format($thread['thread_timestamp']));
        $threadContentTemplate->AddEntry('{thread_content}', $thread['thread_content']);
        $threadContentTemplate->AddEntry('{thread_likes}', $thread['thread_likes'] > 0 ? $thread['thread_likes'] : '');
        // TODO: Add like button or new like widgets for posts if user is logged!

        $threadContentTemplate->AddEntry('{user_id}', $thread['user_id']);
        $threadContentTemplate->AddEntry('{user_name}', Username::Format($thread['rank_format'], $thread['user_name']));
        $threadContentTemplate->AddEntry('{user_avatar}', Avatar::GetPath($this->theme, $thread['user_avatar']));
        $threadContentTemplate->AddEntry('{user_avatar_alt}', $thread['user_name']);
        $threadContentTemplate->AddEntry('{user_rank}', $thread['rank_name']);
        $threadContentTemplate->AddEntry('{user_stats}', $userStatsTemplate->templ);
        $threadContentTemplate->Replace();

        $this->thread = $threadContentTemplate->templ;

        $this->forumDesc .= $thread['thread_title'];
    }

    private function fetchPosts()
    {

    }

    private function Do()
    {
        $this->fetchThread();
        $this->fetchPosts();

        $stylesTemplate = new StylesWidget($this->theme, '/templates/thread/styles.html');

        $headTemplate = new HeadWidget($this->theme, $this->forumName, $this->forumDesc, $stylesTemplate->Template->templ);

        $headerTemplate = new HeaderWidget($this->theme);

        $footerTemplate = new FooterWidget($this->theme);

        $paginationTemplate = new PaginationWidget($this->theme, $this->threadPage, $this->postsCount, $this->maximumResults, 'thread/' . $this->threadID . '/page/');
    
        $this->template = new Template('./themes/' . $this->theme . '/templates/thread/thread.html');
        $this->template->AddEntry('{head}', $headTemplate->Template->templ);
        $this->template->AddEntry('{header}', $headerTemplate->Template->templ);
        $this->template->AddEntry('{thread}', $this->thread);
        $this->template->AddEntry('{posts}', $this->posts);
        $this->template->AddEntry('{pagination}', $this->postsCount > 0 ? $paginationTemplate->Template->templ : '');
        $this->template->AddEntry('{footer}', $footerTemplate->Template->templ);
        $this->template->Render(true);
    }
}

?>