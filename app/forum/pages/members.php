<?php

require_once './app/template/template.php';

require_once './app/helpers/url_manager.php';
require_once './app/helpers/avatar.php';
require_once './app/helpers/username.php';
require_once './app/helpers/time.php';

require_once './app/forum/widgets/styles.php';
require_once './app/forum/widgets/head.php';
require_once './app/forum/widgets/header.php';
require_once './app/forum/widgets/footer.php';
require_once './app/forum/widgets/pagination.php';

class MembersPage
{
    private $template;
    private string $theme;
    private string $forumName;
    private string $forumDesc;
    private $database;

    private string $members = '';
    private string $ServerPath;
    private int $page = 1;
    private int $maximumResults = 6;
    private int $totalMembers;

    public function __construct($db, array $forumData)
    {
        $this->theme = $forumData['forum_theme'];
        $this->forumName = $forumData['forum_name'];
        $this->forumDesc = 'Member list';
        $this->database = $db;
        $this->UrlHandler();
        $this->Do();
    }

    private function UrlHandler()
    {
        $url = explode('/', $_GET['action']);

        $this->ServerPath = UrlManager::GetPath();

        if (($url[1] != 'page' || !is_numeric($url[2])))
        {
            UrlManager::Redirect($this->ServerPath . 'members/page/1');
            return;
        }

        $this->page = $url[2];
    }

    private function FetchMembers()
    {
        if ($this->page <= 0)
        {
            UrlManager::Redirect($this->ServerPath . 'members/page/1');
            return;
        }

        $this->totalMembers = $this->database->Query('SELECT id FROM bit_accounts WHERE bit_accounts.id > 1')->NumRows();

        $totalPages = ceil($this->totalMembers / $this->maximumResults);

        if ($this->page > $totalPages)
        {
            UrlManager::Redirect($this->ServerPath . 'members/page/' . $totalPages);
            return;
        }

        $members = $this->database->Query('SELECT b.id, b.username, b.avatar, b.reg_date, b.reputation, b.last_active, b.rank_id, r.rank_format, r.rank_name,
            (SELECT COUNT(*) FROM bit_threads WHERE user_id = b.id) AS thread_count,
            (SELECT COUNT(*) FROM bit_posts WHERE user_id = b.id) AS post_count
            FROM bit_accounts AS b
            JOIN bit_ranks AS r ON b.rank_id = r.id
            WHERE b.id > 1
            ORDER BY b.id ASC
            LIMIT ?, ?', 
            [(($this->page - 1) * $this->maximumResults), $this->maximumResults]
        )->FetchAll();

        foreach ($members as $member) 
        {
            $memberTemplate = new Template('./themes/' . $this->theme . '/templates/members/member.html');
            $memberTemplate->AddEntry('{id}', $member['id']);
            $memberTemplate->AddEntry('{server_url}', $this->ServerPath);
            $memberTemplate->AddEntry('{avatar}', Avatar::GetPath($this->theme, $member['avatar']));
            $memberTemplate->AddEntry('{username}', Username::Format($member['rank_format'], $member['username']));
            $memberTemplate->AddEntry('{rank}', $member['rank_name']);
            $memberTemplate->AddEntry('{reputation}', $member['reputation']);
            $memberTemplate->AddEntry('{regdate}', $member['reg_date']);
            $memberTemplate->AddEntry('{lastseen}', RelativeTime::Convert($member['last_active']));
            $memberTemplate->AddEntry('{posts}', $member['post_count']);
            $memberTemplate->AddEntry('{threads}', $member['thread_count']);
            $memberTemplate->Replace();

            $this->members .= $memberTemplate->templ;
        }
    }

    private function Do()
    {
        $this->FetchMembers();

        $stylesTemplate = new StylesWidget($this->theme, '/templates/members/styles.html');

        $headTemplate = new HeadWidget($this->theme, $this->forumName, $this->forumDesc, $stylesTemplate->Template->templ);

        $headerTemplate = new HeaderWidget($this->theme);

        $footerTemplate = new FooterWidget($this->theme);

        $paginationTemplate = new PaginationWidget($this->theme, $this->page, $this->totalMembers, $this->maximumResults, 'members/page/');

        $this->template = new Template('./themes/' . $this->theme . '/templates/members/members.html');
        $this->template->AddEntry('{head}', $headTemplate->Template->templ);
        $this->template->AddEntry('{header}', $headerTemplate->Template->templ);
        $this->template->AddEntry('{pagination}', $paginationTemplate->Template->templ);
        $this->template->AddEntry('{members}', $this->members);
        $this->template->AddEntry('{footer}', $footerTemplate->Template->templ);
        $this->template->Render(true);
    }
}

?>