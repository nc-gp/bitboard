<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\Permissions;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;
use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

class SearchPage extends PageBase implements PageInterface
{
    private const SORT_OPTIONS = [
        'relevance' => 'Most relevant',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
    ];

    private const DATE_OPTIONS = [
        'any' => 'Any time',
        'day' => 'Past 24 hours',
        'week' => 'Past week',
        'month' => 'Past month',
        'year' => 'Past year',
    ];

    private const DATE_DAYS = [
        'day' => 1,
        'week' => 7,
        'month' => 30,
        'year' => 365,
    ];

    private int $page = 1;
    private int $maximumResults = 10;
    private int $totalResults = 0;

    private string $query = '';
    private int $forumID = 0;
    private string $author = '';
    private string $date = 'any';
    private string $sort = 'relevance';
    private bool $useFulltext = false;

    private string $results = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'Search';
    }

    private function UrlHandler(): void
    {
        $query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $author = isset($_GET['author']) && is_string($_GET['author']) ? trim($_GET['author']) : '';
        $date = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : 'any';
        $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'relevance';

        $this->query = mb_substr($query, 0, 128);
        $this->author = mb_substr($author, 0, 32);
        $this->date = array_key_exists($date, self::DATE_OPTIONS) ? $date : 'any';
        $this->sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : 'relevance';

        if (isset($_GET['forum']) && is_string($_GET['forum']) && ctype_digit($_GET['forum']))
            $this->forumID = (int) $_GET['forum'];

        if (
            count($this->forumData->actionParameters) < 3 ||
            ($this->forumData->actionParameters[1] ?? '') !== 'page' ||
            !is_numeric($this->forumData->actionParameters[2] ?? '')
        )
            UrlManager::Redirect($this->BuildUrl(1));

        $this->page = (int) $this->forumData->actionParameters[2];

        if ($this->page <= 0)
            UrlManager::Redirect($this->BuildUrl(1));
    }

    private function FilterParameters(): array
    {
        $parameters = [];

        if ($this->query !== '')
            $parameters['q'] = $this->query;

        if ($this->forumID > 0)
            $parameters['forum'] = $this->forumID;

        if ($this->author !== '')
            $parameters['author'] = $this->author;

        if ($this->date !== 'any')
            $parameters['date'] = $this->date;

        if ($this->sort !== 'relevance')
            $parameters['sort'] = $this->sort;

        return $parameters;
    }

    private function BuildUrl(int $page): string
    {
        $url = $this->serverPath . 'search/page/' . $page;
        $parameters = $this->FilterParameters();

        return count($parameters) > 0 ? $url . '?' . http_build_query($parameters) : $url;
    }

    /**
     * Make sure installations created before search existed get the FULLTEXT
     * indexes. If the database user cannot add them, fall back to LIKE queries.
     */
    private function EnsureFulltextIndexes(): bool
    {
        try
        {
            $indexes = $this->database->Query("SELECT table_name, index_name FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND index_name IN ('ft_thread_search', 'ft_post_search')")->FetchAll();

            $existing = [];
            foreach ($indexes as $index)
                $existing[$index['table_name'] . '.' . $index['index_name']] = true;

            if (!isset($existing['bit_threads.ft_thread_search']))
                $this->database->Query('ALTER TABLE bit_threads ADD FULLTEXT INDEX ft_thread_search (thread_title, thread_content)');

            if (!isset($existing['bit_posts.ft_post_search']))
                $this->database->Query('ALTER TABLE bit_posts ADD FULLTEXT INDEX ft_post_search (post_content)');

            return true;
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    /**
     * Build the thread and post filters shared by the count and result queries.
     *
     * @return array [thread conditions, thread params, post conditions, post params]
     */
    private function BuildConditions(bool $fulltext): array
    {
        $threadConditions = [];
        $threadParameters = [];
        $postConditions = [];
        $postParameters = [];

        if ($fulltext)
        {
            $threadConditions[] = 'MATCH(t.thread_title, t.thread_content) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $threadParameters[] = $this->query;

            $postConditions[] = 'MATCH(p.post_content) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $postParameters[] = $this->query;
        }
        else
        {
            $like = '%' . addcslashes($this->query, '\\%_') . '%';

            $threadConditions[] = '(t.thread_title LIKE ? OR t.thread_content LIKE ?)';
            $threadParameters[] = $like;
            $threadParameters[] = $like;

            $postConditions[] = 'p.post_content LIKE ?';
            $postParameters[] = $like;
        }

        if ($this->forumID > 0)
        {
            $threadConditions[] = 't.forum_id = ?';
            $threadParameters[] = $this->forumID;

            $postConditions[] = 't.forum_id = ?';
            $postParameters[] = $this->forumID;
        }

        if ($this->author !== '')
        {
            $authorLike = '%' . addcslashes($this->author, '\\%_') . '%';

            $threadConditions[] = 'u.username LIKE ?';
            $threadParameters[] = $authorLike;

            $postConditions[] = 'u.username LIKE ?';
            $postParameters[] = $authorLike;
        }

        $days = self::DATE_DAYS[$this->date] ?? 0;
        if ($days > 0)
        {
            $threadConditions[] = 't.thread_timestamp >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
            $postConditions[] = 'p.post_timestamp >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
        }

        if (!SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED))
        {
            $lockedFilter = 'f.is_locked = 0 AND (sf.is_locked IS NULL OR sf.is_locked = 0)';

            $threadConditions[] = $lockedFilter;
            $postConditions[] = $lockedFilter;
        }

        return [$threadConditions, $threadParameters, $postConditions, $postParameters];
    }

    /**
     * Build the count and page queries for the combined thread/post result set.
     *
     * @return array [count SQL, result SQL, count params]
     */
    private function BuildSearchQueries(bool $fulltext): array
    {
        [$threadConditions, $threadParameters, $postConditions, $postParameters] = $this->BuildConditions($fulltext);

        $threadRelevance = $fulltext
            ? 'MATCH(t.thread_title, t.thread_content) AGAINST (? IN NATURAL LANGUAGE MODE)'
            : '0';
        $postRelevance = $fulltext
            ? 'MATCH(p.post_content) AGAINST (? IN NATURAL LANGUAGE MODE)'
            : '0';

        $threadSelectParameters = $fulltext ? [$this->query] : [];
        $postSelectParameters = $fulltext ? [$this->query] : [];

        $threadSelect = 'SELECT \'thread\' AS result_type, t.id AS thread_id, 0 AS post_id,
            t.thread_title AS title, t.thread_content AS content, t.thread_timestamp AS created_at,
            t.forum_id, t.subforum_id, u.id AS author_id, u.username, u.rank_id, r.rank_format,
            f.forum_name, sf.subforum_name, ' . $threadRelevance . ' AS relevance
            FROM bit_threads AS t
            JOIN bit_accounts AS u ON u.id = t.user_id
            JOIN bit_ranks AS r ON r.id = u.rank_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE ' . implode(' AND ', $threadConditions);

        $postSelect = 'SELECT \'post\' AS result_type, t.id AS thread_id, p.id AS post_id,
            t.thread_title AS title, p.post_content AS content, p.post_timestamp AS created_at,
            t.forum_id, t.subforum_id, u.id AS author_id, u.username, u.rank_id, r.rank_format,
            f.forum_name, sf.subforum_name, ' . $postRelevance . ' AS relevance
            FROM bit_posts AS p
            JOIN bit_threads AS t ON t.id = p.thread_id
            JOIN bit_accounts AS u ON u.id = p.user_id
            JOIN bit_ranks AS r ON r.id = u.rank_id
            JOIN bit_forums AS f ON f.id = t.forum_id
            LEFT JOIN bit_subforums AS sf ON sf.id = t.subforum_id
            WHERE ' . implode(' AND ', $postConditions);

        $union = $threadSelect . ' UNION ALL ' . $postSelect;

        $orderBy = match ($this->sort)
        {
            'newest' => 'created_at DESC, relevance DESC, thread_id DESC, post_id DESC',
            'oldest' => 'created_at ASC, relevance DESC, thread_id ASC, post_id ASC',
            default => 'relevance DESC, created_at DESC, thread_id DESC, post_id DESC',
        };

        $countSql = 'SELECT COUNT(*) AS result_count FROM (' . $union . ') AS search_results';
        $resultSql = 'SELECT * FROM (' . $union . ') AS search_results ORDER BY ' . $orderBy . ' LIMIT ?, ?';

        $countParameters = array_merge($threadSelectParameters, $threadParameters, $postSelectParameters, $postParameters);

        return [$countSql, $resultSql, $countParameters];
    }

    private function Excerpt(string $content): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $content) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (mb_strlen($text) > 220)
            $text = mb_substr($text, 0, 217) . '...';

        return $text;
    }

    private function BuildLocation(array $result): string
    {
        $location = '<a href="' . $this->serverPath . 'forum/' . (int) $result['forum_id'] . '/page/1">'
            . Template::Escape($result['forum_name']) . '</a>';

        if (!empty($result['subforum_name']))
            $location .= ' / <a href="' . $this->serverPath . 'subforum/' . (int) $result['subforum_id'] . '/page/1">'
                . Template::Escape($result['subforum_name']) . '</a>';

        return $location;
    }

    private function FetchResults(): void
    {
        if ($this->query === '')
        {
            $promptTemplate = new Template('search', 'prompt');
            $this->results = $promptTemplate->template;

            return;
        }

        $this->useFulltext = $this->EnsureFulltextIndexes();

        [$countSql, $resultSql, $countParameters] = $this->BuildSearchQueries($this->useFulltext);

        $count = count($countParameters) > 0
            ? $this->database->Query($countSql, $countParameters)->FetchArray()
            : $this->database->Query($countSql)->FetchArray();

        $this->totalResults = (int) ($count['result_count'] ?? 0);

        if ($this->totalResults <= 0)
        {
            if ($this->page > 1)
                UrlManager::Redirect($this->BuildUrl(1));

            $emptyTemplate = new Template('search', 'empty');
            $this->results = $emptyTemplate->template;

            return;
        }

        $totalPages = (int) ceil($this->totalResults / $this->maximumResults);

        if ($this->page > $totalPages)
            UrlManager::Redirect($this->BuildUrl($totalPages));

        $resultParameters = $countParameters;
        $resultParameters[] = ($this->page - 1) * $this->maximumResults;
        $resultParameters[] = $this->maximumResults;

        $rows = $this->database->Query($resultSql, $resultParameters)->FetchAll();

        foreach ($rows as $result)
        {
            $resultTemplate = new Template('search', 'result');
            $resultTemplate->AddEntry('{url}', $this->serverPath . 'thread/' . (int) $result['thread_id'] . '/page/1');
            $resultTemplate->AddEscapedEntry('{title}', $result['title']);
            $resultTemplate->AddEntry('{excerpt}', Template::Escape($this->Excerpt($result['content'])));
            $resultTemplate->AddEntry('{location}', $this->BuildLocation($result));
            $resultTemplate->AddEscapedEntry('{date}', RelativeTime::Convert($result['created_at']));
            $resultTemplate->AddEscapedEntry('{type_label}', $result['result_type'] === 'thread' ? 'Thread' : 'Reply');
            $resultTemplate->AddEscapedEntry('{icon}', $result['result_type'] === 'thread' ? 'ti-message-2' : 'ti-message-reply');

            $author = '<a href="' . $this->serverPath . 'profile/' . (int) $result['author_id'] . '">'
                . UsernameUtils::Format($result['rank_format'], $result['username']) . '</a>';
            $resultTemplate->AddEntry('{author}', $author);

            $resultTemplate->Replace();
            $this->results .= $resultTemplate->template;
        }
    }

    private function BuildOptions(): array
    {
        $forums = $this->database->Query('SELECT id, forum_name FROM bit_forums ORDER BY forum_position ASC, id ASC')->FetchAll();

        $forumOptions = '<option value="0">All forums</option>';
        foreach ($forums as $forum)
        {
            $forumOptions .= '<option value="' . (int) $forum['id'] . '"'
                . ($this->forumID === (int) $forum['id'] ? ' selected' : '') . '>'
                . Template::Escape($forum['forum_name']) . '</option>';
        }

        $dateOptions = '';
        foreach (self::DATE_OPTIONS as $value => $label)
        {
            $dateOptions .= '<option value="' . Template::Escape($value) . '"'
                . ($this->date === $value ? ' selected' : '') . '>'
                . Template::Escape($label) . '</option>';
        }

        $sortOptions = '';
        foreach (self::SORT_OPTIONS as $value => $label)
        {
            $sortOptions .= '<option value="' . Template::Escape($value) . '"'
                . ($this->sort === $value ? ' selected' : '') . '>'
                . Template::Escape($label) . '</option>';
        }

        return [$forumOptions, $dateOptions, $sortOptions];
    }

    private function BuildSummary(): string
    {
        if ($this->query === '')
            return '';

        $label = $this->totalResults === 1 ? 'result' : 'results';

        return '<strong>' . $this->totalResults . '</strong> ' . $label
            . ' for &ldquo;' . Template::Escape($this->query) . '&rdquo;';
    }

    public function Do(): void
    {
        $this->FetchResults();

        [$forumOptions, $dateOptions, $sortOptions] = $this->BuildOptions();

        $paginationTemplate = new PaginationWidget(
            $this->page,
            $this->totalResults,
            $this->maximumResults,
            'search/page/',
            $this->FilterParameters()
        );

        $this->template = new Template('search', 'search');
        $this->template->AddEscapedEntry('{query}', $this->query);
        $this->template->AddEscapedEntry('{author}', $this->author);
        $this->template->AddEntry('{forum_options}', $forumOptions);
        $this->template->AddEntry('{date_options}', $dateOptions);
        $this->template->AddEntry('{sort_options}', $sortOptions);
        $this->template->AddEntry('{summary}', $this->BuildSummary());
        $this->template->AddEntry('{results}', $this->results);
        $this->template->AddEntry('{pagination}', $this->totalResults > $this->maximumResults ? $paginationTemplate->Template->template : '');

        parent::RenderPage('search');
    }
}

?>
