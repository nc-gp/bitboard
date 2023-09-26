<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\Avatar;
use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;
use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

class MembersPage extends PageBase implements PageInterface
{
    private int $page = 1;
    private int $maximumResults = 6;
    private int $totalMembers;

    private string $members = '';

    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->UrlHandler();
        $this->forumDesc = 'Members list';
    }

    private function UrlHandler()
    {
        $url = explode('/', $_GET['action']);

        if (($this->forumData['actionParameters'][1] != 'page' || !is_numeric($this->forumData['actionParameters'][2])))
        {
            UrlManager::Redirect($this->serverPath . 'members/page/1');
            return;
        }

        $this->page = $this->forumData['actionParameters'][2];
    }

    private function FetchMembers()
    {
        if ($this->page <= 0)
        {
            UrlManager::Redirect($this->serverPath . 'members/page/1');
            return;
        }

        $this->totalMembers = $this->database->Query('SELECT id FROM bit_accounts WHERE bit_accounts.id > 1')->NumRows();

        $totalPages = ceil($this->totalMembers / $this->maximumResults);

        if ($this->page > $totalPages)
        {
            UrlManager::Redirect($this->serverPath . 'members/page/' . $totalPages);
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

        $onlineTemplate = '';
        foreach ($members as $member) 
        {
            if(RelativeTime::IsUserActive($member['last_active']))
                $onlineTemplate = new Template('./themes/' . $this->theme . '/templates/members/member_online.html');
            else
                $onlineTemplate = new Template('./themes/' . $this->theme . '/templates/members/member_offline.html');

            $memberTemplate = new Template('./themes/' . $this->theme . '/templates/members/member.html');
            $memberTemplate->AddEntry('{id}', $member['id']);
            $memberTemplate->AddEntry('{server_url}', $this->serverPath);
            $memberTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($this->theme, $member['avatar']));
            $memberTemplate->AddEntry('{username}', UsernameUtils::Format($member['rank_format'], $member['username']));
            $memberTemplate->AddEntry('{rank}', $member['rank_name']);
            $memberTemplate->AddEntry('{reputation}', $member['reputation']);
            $memberTemplate->AddEntry('{regdate}', RelativeTime::Format($member['reg_date'], false));
            $memberTemplate->AddEntry('{lastseen}', RelativeTime::Convert($member['last_active']));
            $memberTemplate->AddEntry('{posts}', $member['post_count']);
            $memberTemplate->AddEntry('{threads}', $member['thread_count']);
            $memberTemplate->AddEntry('{online}', $onlineTemplate->templ);
            $memberTemplate->Replace();

            $this->members .= $memberTemplate->templ;
        }
    }

    public function Do()
    {
        $this->FetchMembers();

        $paginationTemplate = new PaginationWidget($this->theme, $this->page, $this->totalMembers, $this->maximumResults, 'members/page/');

        $this->template = new Template('./themes/' . $this->theme . '/templates/members/members.html');
        $this->template->AddEntry('{pagination}', $paginationTemplate->Template->templ);
        $this->template->AddEntry('{members}', $this->members);

        parent::RenderPage('./templates/members/styles.html');
    }
}

?>