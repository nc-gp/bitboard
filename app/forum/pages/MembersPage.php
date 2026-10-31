<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;
use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

class MembersPage extends PageBase implements PageInterface
{
    private const SORT_OPTIONS = [
        'newest' => 'Newest members',
        'oldest' => 'Oldest members',
        'username' => 'Username',
        'reputation' => 'Reputation',
        'posts' => 'Most posts',
        'threads' => 'Most threads',
    ];

    private int $page = 1;
    private int $maximumResults = 6;
    private int $totalMembers = 0;
    private string $search = '';
    private string $sort = 'newest';

    private string $members = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'Members list';
        $this->ogTitle = 'Members - ' . $this->forumName;
        $this->ogDescription = 'Browse the member directory.';
    }

    private function UrlHandler(): void
    {
        $search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'newest';

        $this->search = mb_substr($search, 0, 64);
        $this->sort = array_key_exists($sort, self::SORT_OPTIONS) ? $sort : 'newest';

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

        if ($this->search !== '')
            $parameters['q'] = $this->search;

        if ($this->sort !== 'newest')
            $parameters['sort'] = $this->sort;

        return $parameters;
    }

    private function BuildUrl(int $page): string
    {
        $url = $this->serverPath . 'members/page/' . $page;
        $parameters = $this->FilterParameters();

        return count($parameters) > 0 ? $url . '?' . http_build_query($parameters) : $url;
    }

    private function FetchMembers(): void
    {
        $where = 'b.is_system = 0';
        $parameters = [];

        if ($this->search !== '')
        {
            $where .= ' AND b.username LIKE ?';
            $parameters[] = '%' . $this->search . '%';
        }

        $countSql = 'SELECT COUNT(*) AS member_count FROM bit_accounts AS b WHERE ' . $where;
        $count = count($parameters) > 0
            ? $this->database->Query($countSql, $parameters)->FetchArray()
            : $this->database->Query($countSql)->FetchArray();

        $this->totalMembers = (int) ($count['member_count'] ?? 0);

        if ($this->totalMembers <= 0)
        {
            $emptyTemplate = new Template('members', 'members_empty');
            $this->members = $emptyTemplate->template;
            return;
        }

        $totalPages = (int) ceil($this->totalMembers / $this->maximumResults);

        if ($this->page > $totalPages)
            UrlManager::Redirect($this->BuildUrl($totalPages));

        $orderBy = match ($this->sort)
        {
            'oldest' => 'b.reg_date ASC, b.id ASC',
            'username' => 'b.username ASC, b.id ASC',
            'reputation' => 'b.reputation DESC, b.id ASC',
            'posts' => 'post_count DESC, b.id ASC',
            'threads' => 'thread_count DESC, b.id ASC',
            default => 'b.reg_date DESC, b.id DESC',
        };

        $queryParameters = $parameters;
        $queryParameters[] = ($this->page - 1) * $this->maximumResults;
        $queryParameters[] = $this->maximumResults;

        $members = $this->database->Query('SELECT b.id, b.username, b.avatar, b.reg_date, b.reputation, b.last_active, b.rank_id, r.rank_format, r.rank_name,
            (SELECT COUNT(*) FROM bit_threads WHERE user_id = b.id) AS thread_count,
            (SELECT COUNT(*) FROM bit_posts WHERE user_id = b.id) AS post_count
            FROM bit_accounts AS b
            JOIN bit_ranks AS r ON b.rank_id = r.id
            WHERE ' . $where . '
            ORDER BY ' . $orderBy . '
            LIMIT ?, ?',
            $queryParameters
        )->FetchAll();

        $memberContact = '';
        if(SessionManager::IsLogged())
        {
            $memberContact = new Template('members', 'member_contact');
            $memberContact = $memberContact->template;
        }

        $onlineTemplate = '';
        foreach ($members as $member) 
        {
            if(RelativeTime::IsUserActive($member['last_active']))
                $onlineTemplate = new Template('members', 'member_online');
            else
                $onlineTemplate = new Template('members', 'member_offline');

            $memberTemplate = new Template('members', 'member');
            $memberTemplate->AddEscapedEntry('{id}', $member['id']);
            $memberTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($member['avatar']));
            $memberTemplate->AddEntry('{username}', UsernameUtils::Format($member['rank_format'], $member['username']));
            $memberTemplate->AddEscapedEntry('{rank}', $member['rank_name']);
            $memberTemplate->AddEscapedEntry('{reputation}', $member['reputation']);
            $memberTemplate->AddEscapedEntry('{regdate}', RelativeTime::Format($member['reg_date'], false));
            $memberTemplate->AddEscapedEntry('{lastseen}', RelativeTime::Convert($member['last_active']));
            $memberTemplate->AddEscapedEntry('{posts}', $member['post_count']);
            $memberTemplate->AddEscapedEntry('{threads}', $member['thread_count']);
            $memberTemplate->AddEntry('{online}', $onlineTemplate->template);
            $memberTemplate->AddEntry('{contact}', $memberContact);
            $memberTemplate->Replace();

            $this->members .= $memberTemplate->template;
        }
    }

    public function Do(): void
    {
        $this->FetchMembers();

        $sortOptions = '';
        foreach (self::SORT_OPTIONS as $value => $label)
        {
            $sortOptions .= '<option value="' . Template::Escape($value) . '"'
                . ($this->sort === $value ? ' selected' : '') . '>'
                . Template::Escape($label) . '</option>';
        }

        $paginationTemplate = new PaginationWidget(
            $this->page,
            $this->totalMembers,
            $this->maximumResults,
            'members/page/',
            $this->FilterParameters()
        );

        $this->template = new Template('members', 'members');
        $this->template->AddEscapedEntry('{search}', $this->search);
        $this->template->AddEntry('{sort_options}', $sortOptions);
        $this->template->AddEntry('{pagination}', $this->totalMembers > $this->maximumResults ? $paginationTemplate->Template->template : '');
        $this->template->AddEntry('{members}', $this->members);

        parent::RenderPage('members');
    }
}

?>
