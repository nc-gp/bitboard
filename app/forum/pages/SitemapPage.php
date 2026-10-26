<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\Slug;
use App\Classes\UrlManager;
use App\Classes\Xml;
use App\Interfaces\PageInterface;

class SitemapPage implements PageInterface
{
    private Database $database;

    public function __construct(Database $database, object $data)
    {
        $this->database = $database;
    }

    private function LastModified(array $thread): string
    {
        $latest = 0;

        foreach ([$thread['thread_timestamp'], $thread['thread_edited_timestamp'], $thread['last_post']] as $value)
        {
            if ($value === null || $value === '')
                continue;

            $time = strtotime($value);
            if ($time !== false && $time > $latest)
                $latest = $time;
        }

        return $latest > 0 ? date('c', $latest) : '';
    }

    public function Do()
    {
        $base = UrlManager::GetPath();
        $urls = [];

        $urls[] = '<url><loc>' . Xml::Escape($base) . '</loc></url>';
        $urls[] = '<url><loc>' . Xml::Escape($base . 'members/page/1') . '</loc></url>';

        $forums = $this->database->Query('SELECT id FROM bit_forums WHERE is_locked = 0 ORDER BY id ASC')->FetchAll();
        foreach ($forums as $forum)
            $urls[] = '<url><loc>' . Xml::Escape($base . 'forum/' . (int) $forum['id'] . '/page/1') . '</loc></url>';

        $subforums = $this->database->Query('SELECT sf.id FROM bit_subforums AS sf
            JOIN bit_forums AS f ON f.id = sf.forum_id
            WHERE sf.is_locked = 0 AND f.is_locked = 0
            ORDER BY sf.id ASC')->FetchAll();

        foreach ($subforums as $subforum)
            $urls[] = '<url><loc>' . Xml::Escape($base . 'subforum/' . (int) $subforum['id'] . '/page/1') . '</loc></url>';

        $threads = $this->database->Query('SELECT t.id, t.thread_title, t.thread_timestamp, t.thread_edited_timestamp,
                (SELECT MAX(p.post_timestamp) FROM bit_posts AS p WHERE p.thread_id = t.id) AS last_post,
                f.is_locked AS forum_locked, sf.is_locked AS subforum_locked
            FROM bit_threads AS t
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE f.is_locked = 0 AND (sf.is_locked IS NULL OR sf.is_locked = 0)
            ORDER BY t.id ASC')->FetchAll();

        foreach ($threads as $thread)
        {
            $lastmod = $this->LastModified($thread);
            $lastmodTag = $lastmod !== '' ? '<lastmod>' . Xml::Escape($lastmod) . '</lastmod>' : '';

            $urls[] = '<url><loc>' . Xml::Escape(UrlManager::ThreadUrl(
                (int) $thread['id'],
                Slug::FromTitle($thread['thread_title'])
            )) . '</loc>' . $lastmodTag . '</url>';
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header_remove('Pragma');
        header('Cache-Control: public, max-age=300');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo implode("\n", $urls) . "\n";
        echo '</urlset>';
    }
}

?>
