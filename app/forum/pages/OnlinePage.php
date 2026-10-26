<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\AvatarUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;
use App\Interfaces\PageInterface;

use App\Forum\Controllers\AccountController;
use App\Forum\Widgets\PaginationWidget;

class OnlinePage extends PageBase implements PageInterface
{
    private int $page = 1;
    private int $maximumResults = 24;
    private int $totalOnline = 0;

    private string $users = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'Members online';
        $this->ogTitle = 'Members online - ' . $this->forumName;
        $this->ogDescription = 'See which members are currently online.';
    }

    private function UrlHandler(): void
    {
        if (
            count($this->forumData->actionParameters) < 3 ||
            ($this->forumData->actionParameters[1] ?? '') !== 'page' ||
            !is_numeric($this->forumData->actionParameters[2] ?? '')
        )
            UrlManager::Redirect($this->serverPath . 'online/page/1');

        $this->page = (int) $this->forumData->actionParameters[2];

        if ($this->page <= 0)
            UrlManager::Redirect($this->serverPath . 'online/page/1');

        $this->canonicalUrl = $this->serverPath . 'online/page/' . $this->page;
    }

    private function FetchOnline(): void
    {
        $this->totalOnline = AccountController::CountOnline($this->database);

        if ($this->totalOnline <= 0)
        {
            if ($this->page > 1)
                UrlManager::Redirect($this->serverPath . 'online/page/1');

            $emptyTemplate = new Template('online', 'empty');
            $this->users = $emptyTemplate->template;

            return;
        }

        $totalPages = (int) ceil($this->totalOnline / $this->maximumResults);

        if ($this->page > $totalPages)
            UrlManager::Redirect($this->serverPath . 'online/page/' . $totalPages);

        $users = AccountController::GetOnline(
            $this->database,
            ($this->page - 1) * $this->maximumResults,
            $this->maximumResults
        );

        foreach ($users as $user)
        {
            $userTemplate = new Template('online', 'user');
            $userTemplate->AddEscapedEntry('{id}', $user['id']);
            $userTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($user['avatar']));
            $userTemplate->AddEscapedEntry('{username_plain}', $user['username']);
            $userTemplate->AddEntry('{username}', UsernameUtils::Format($user['rank_format'], $user['username']));
            $userTemplate->AddEscapedEntry('{rank}', $user['rank_name']);
            $userTemplate->AddEscapedEntry('{last_seen}', RelativeTime::Convert($user['last_active']));
            $userTemplate->Replace();

            $this->users .= $userTemplate->template;
        }
    }

    public function Do(): void
    {
        $this->FetchOnline();

        $summary = $this->totalOnline === 1
            ? '1 member is active in the last 15 minutes.'
            : $this->totalOnline . ' members are active in the last 15 minutes.';

        if ($this->totalOnline <= 0)
            $summary = 'No members have been active in the last 15 minutes.';

        $paginationTemplate = new PaginationWidget(
            $this->page,
            $this->totalOnline,
            $this->maximumResults,
            'online/page/'
        );

        $this->template = new Template('online', 'online');
        $this->template->AddEscapedEntry('{summary}', $summary);
        $this->template->AddEntry('{users}', $this->users);
        $this->template->AddEntry('{pagination}', $this->totalOnline > $this->maximumResults ? $paginationTemplate->Template->template : '');

        parent::RenderPage('online');
    }
}

?>
