<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\Avatar;
use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\SessionManager;
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
            $memberTemplate->AddEntry('{id}', $member['id']);
            $memberTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($this->theme, $member['avatar']));
            $memberTemplate->AddEntry('{username}', UsernameUtils::Format($member['rank_format'], $member['username']));
            $memberTemplate->AddEntry('{rank}', $member['rank_name']);
            $memberTemplate->AddEntry('{reputation}', $member['reputation']);
            $memberTemplate->AddEntry('{regdate}', RelativeTime::Format($member['reg_date'], false));
            $memberTemplate->AddEntry('{lastseen}', RelativeTime::Convert($member['last_active']));
            $memberTemplate->AddEntry('{posts}', $member['post_count']);
            $memberTemplate->AddEntry('{threads}', $member['thread_count']);
            $memberTemplate->AddEntry('{online}', $onlineTemplate->template);
            $memberTemplate->AddEntry('{contact}', $memberContact);
            $memberTemplate->Replace();

            $this->members .= $memberTemplate->template;
        }
    }

    public function Do()
    {
        $this->FetchMembers();

        $paginationTemplate = new PaginationWidget($this->page, $this->totalMembers, $this->maximumResults, 'members/page/');

        $this->template = new Template('members', 'members');
        $this->template->AddEntry('{pagination}', $this->totalMembers > $this->maximumResults ? $paginationTemplate->Template->template : '');
        $this->template->AddEntry('{members}', $this->members);

        parent::RenderPage('members');
    }
}

?>