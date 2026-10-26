<?php

namespace App\Forum\Pages;

use App\Classes\AvatarUtils;
use App\Classes\Database;
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

use App\Interfaces\PageInterface;

class ProfilePage extends PageBase implements PageInterface
{
    private int $profileID = 0;
    private array $profile = [];

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (count($this->forumData->actionParameters) !== 2 || !is_numeric($this->forumData->actionParameters[1]))
            throw new PageNotFoundException();

        $this->profileID = (int) $this->forumData->actionParameters[1];

        if ($this->profileID <= 0)
            throw new PageNotFoundException();

        $this->profile = $this->database->Query('SELECT a.*, r.rank_name, r.rank_format
            FROM bit_accounts AS a
            JOIN bit_ranks AS r ON r.id = a.rank_id
            WHERE a.id = ?', $this->profileID)->FetchArray();

        if (empty($this->profile))
            throw new PageNotFoundException();

        $counts = $this->database->Query('SELECT
            (SELECT COUNT(*) FROM bit_threads WHERE user_id = ?) AS thread_count,
            (SELECT COUNT(*) FROM bit_posts WHERE user_id = ?) AS post_count',
            $this->profileID,
            $this->profileID
        )->FetchArray();

        $this->profile['thread_count'] = (int) ($counts['thread_count'] ?? 0);
        $this->profile['post_count'] = (int) ($counts['post_count'] ?? 0);
        $this->forumDesc = 'Profile of ' . $this->profile['username'];
        $this->canonicalUrl = $this->serverPath . 'profile/' . $this->profileID;
        $this->ogType = 'profile';
        $this->ogTitle = $this->profile['username'] . ' - ' . $this->forumName;
        $this->ogDescription = 'Profile of ' . $this->profile['username'] . ' on ' . $this->forumName . '.';
        $this->ogImage = AvatarUtils::GetPath($this->profile['avatar']);
    }

    private function GetRecentThreads(): string
    {
        $visibility = SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED)
            ? ''
            : ' AND f.is_locked = 0 AND (s.id IS NULL OR s.is_locked = 0)';

        $threads = $this->database->Query('SELECT t.id, t.thread_title, t.thread_timestamp,
            (SELECT COUNT(*) FROM bit_posts AS p WHERE p.thread_id = t.id) AS post_count
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS s ON s.id = t.subforum_id
            WHERE t.user_id = ?' . $visibility . '
            ORDER BY t.thread_timestamp DESC, t.id DESC
            LIMIT 5', $this->profileID)->FetchAll();

        if (count($threads) <= 0)
            return '<p class="profile-empty">No threads yet.</p>';

        $html = '';
        foreach ($threads as $thread)
        {
            $template = new Template('profile', 'thread');
            $template->AddEscapedEntry('{id}', $thread['id']);
            $template->AddEscapedEntry('{thread_title}', $thread['thread_title']);
            $template->AddEntry('{thread_url}', UrlManager::ThreadUrl(
                (int) $thread['id'],
                Slug::FromTitle($thread['thread_title'])
            ));
            $template->AddEscapedEntry('{thread_date}', RelativeTime::Convert($thread['thread_timestamp']));
            $template->AddEscapedEntry('{post_count}', $thread['post_count']);
            $template->Replace();
            $html .= $template->template;
        }

        return $html;
    }

    private function GetRecentPosts(): string
    {
        $visibility = SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED)
            ? ''
            : ' AND f.is_locked = 0 AND (s.id IS NULL OR s.is_locked = 0)';

        $posts = $this->database->Query('SELECT p.id, p.post_content, p.post_timestamp,
            t.id AS thread_id, t.thread_title,
            COALESCE(s.subforum_name, f.forum_name) AS location
            FROM bit_posts AS p
            JOIN bit_threads AS t ON t.id = p.thread_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS s ON s.id = t.subforum_id
            WHERE p.user_id = ?' . $visibility . '
            ORDER BY p.post_timestamp DESC, p.id DESC
            LIMIT 5', $this->profileID)->FetchAll();

        if (count($posts) <= 0)
            return '<p class="profile-empty">No posts yet.</p>';

        $html = '';
        foreach ($posts as $post)
        {
            $excerpt = Text::Excerpt($post['post_content'], 180);

            $template = new Template('profile', 'post');
            $template->AddEscapedEntry('{thread_id}', $post['thread_id']);
            $template->AddEscapedEntry('{thread_title}', $post['thread_title']);
            $template->AddEntry('{thread_url}', UrlManager::ThreadUrl(
                (int) $post['thread_id'],
                Slug::FromTitle($post['thread_title'])
            ));
            $template->AddEscapedEntry('{post_excerpt}', $excerpt);
            $template->AddEscapedEntry('{post_date}', RelativeTime::Convert($post['post_timestamp']));
            $template->AddEscapedEntry('{location}', $post['location']);
            $template->Replace();
            $html .= $template->template;
        }

        return $html;
    }

    public function Do(): void
    {
        $online = RelativeTime::IsUserActive($this->profile['last_active']);

        $this->template = new Template('profile', 'profile');
        $this->template->AddEntry('{user_avatar}', AvatarUtils::GetPath($this->profile['avatar']));
        $this->template->AddEscapedEntry('{username_plain}', $this->profile['username']);
        $this->template->AddEntry('{user_name}', UsernameUtils::Format($this->profile['rank_format'], $this->profile['username']));
        $this->template->AddEscapedEntry('{user_rank}', $this->profile['rank_name']);
        $this->template->AddEscapedEntry('{user_reputation}', $this->profile['reputation']);
        $this->template->AddEscapedEntry('{thread_count}', $this->profile['thread_count']);
        $this->template->AddEscapedEntry('{post_count}', $this->profile['post_count']);
        $this->template->AddEscapedEntry('{online_class}', $online ? 'is-online' : 'is-offline');
        $this->template->AddEscapedEntry('{joined}', RelativeTime::Format($this->profile['reg_date'], false));
        $this->template->AddEscapedEntry('{last_seen}', RelativeTime::Convert($this->profile['last_active']));
        $this->template->AddEntry('{user_threads}', $this->GetRecentThreads());
        $this->template->AddEntry('{user_posts}', $this->GetRecentPosts());

        parent::RenderPage('profile');
    }
}

?>
