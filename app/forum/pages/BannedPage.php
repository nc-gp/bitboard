<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\Permissions;
use App\Interfaces\PageInterface;

class BannedPage extends PageBase implements PageInterface
{
    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->forumDesc = 'You are banned!';
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if(Permissions::hasPermission($_SESSION['bitboard_user']['permissions'], Permissions::VIEWING_FORUM))
            UrlManager::Redirect(UrlManager::GetPath());
    }

    public function Do()
    {
        $this->template = new Template('./themes/' . $this->theme . '/templates/banned/banned.html');
        $this->template->AddEntry('{server_url}', $this->serverPath);
        parent::RenderPage('/templates/banned/styles.html');
    }
}

?>