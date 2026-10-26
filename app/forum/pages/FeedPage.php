<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\RelativeTime;
use App\Classes\Slug;
use App\Classes\Text;
use App\Classes\UrlManager;
use App\Classes\Xml;
use App\Interfaces\PageInterface;

class FeedPage implements PageInterface
{
    private Database $database;
    private object $data;

    public function __construct(Database $database, object $data)
    {
        $this->database = $database;
        $this->data = $data;
    }

    private function LatestActivity(array $thread): string
    {
        $latest = strtotime($thread['thread_timestamp']) ?: 0;

        $edited = strtotime($thread['thread_edited_timestamp']) ?: 0;
        if ($edited > $latest)
            $latest = $edited;

        if (!empty($thread['last_post']))
        {
            $lastPost = strtotime($thread['last_post']) ?: 0;
            if ($lastPost > $latest)
                $latest = $lastPost;
        }

        return $latest > 0 ? date('c', $latest) : RelativeTime::Iso($thread['thread_timestamp']);
    }

    public function Do()
    {
        $base = UrlManager::GetPath();
        $feedUrl = $base . 'feed.xml';

        $threads = $this->database->Query('SELECT t.id, t.thread_title, t.thread_content, t.thread_timestamp, t.thread_edited_timestamp,
                a.username, f.forum_name,
                (SELECT MAX(p.post_timestamp) FROM bit_posts AS p WHERE p.thread_id = t.id) AS last_post,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_accounts AS a ON a.id = t.user_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE f.is_locked = 0 AND (sf.is_locked IS NULL OR sf.is_locked = 0)
            ORDER BY COALESCE(last_post, t.thread_timestamp) DESC, t.id DESC
            LIMIT 20')->FetchAll();

        $entries = [];
        $latest = 0;

        foreach ($threads as $thread)
        {
            $threadUrl = UrlManager::ThreadUrl(
                (int) $thread['id'],
                Slug::FromTitle($thread['thread_title'])
            );
            $updated = $this->LatestActivity($thread);
            $updatedTime = strtotime($updated) ?: 0;

            if ($updatedTime > $latest)
                $latest = $updatedTime;

            $summary = Text::Excerpt($thread['thread_content'], 240);
            if ($summary === '')
                $summary = $thread['thread_title'];

            $entry = '<entry>';
            $entry .= '<title>' . Xml::Escape($thread['thread_title']) . '</title>';
            $entry .= '<link rel="alternate" type="text/html" href="' . Xml::Escape($threadUrl) . '"/>';
            $entry .= '<id>' . Xml::Escape($threadUrl) . '</id>';
            $entry .= '<updated>' . Xml::Escape($updated) . '</updated>';
            $entry .= '<published>' . Xml::Escape(RelativeTime::Iso($thread['thread_timestamp'])) . '</published>';
            $entry .= '<author><name>' . Xml::Escape($thread['username']) . '</name></author>';
            $entry .= '<category term="' . Xml::Escape($thread['forum_name']) . '"/>';
            $entry .= '<summary type="text">' . Xml::Escape($summary) . '</summary>';
            $entry .= '</entry>';

            $entries[] = $entry;
        }

        $updated = $latest > 0 ? date('c', $latest) : date('c');

        header('Content-Type: application/atom+xml; charset=UTF-8');
        header_remove('Pragma');
        header('Cache-Control: public, max-age=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en">' . "\n";
        echo '<title>' . Xml::Escape($this->data->forum_name) . '</title>' . "\n";
        echo '<subtitle>' . Xml::Escape($this->data->forum_description) . '</subtitle>' . "\n";
        echo '<link rel="self" type="application/atom+xml" href="' . Xml::Escape($feedUrl) . '"/>' . "\n";
        echo '<link rel="alternate" type="text/html" href="' . Xml::Escape($base) . '"/>' . "\n";
        echo '<id>' . Xml::Escape($feedUrl) . '</id>' . "\n";
        echo '<updated>' . Xml::Escape($updated) . '</updated>' . "\n";
        echo '<author><name>' . Xml::Escape($this->data->forum_name) . '</name></author>' . "\n";
        echo implode("\n", $entries) . "\n";
        echo '</feed>';
    }
}

?>
