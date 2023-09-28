<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\UrlManager;
use App\Interfaces\PageInterface;
use App\Classes\Template;

class AdminPage extends PageBase implements PageInterface
{
    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->UrlHandler();
        $this->forumDesc = 'Admin panel';
    }

    private function UrlHandler()
    {
        if(!$_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
        {
            UrlManager::Redirect($this->serverPath);
            return;
        }
    }

    public function Do()
    {
        $this->template = new Template('admin', 'admin');

        parent::RenderPage('admin');
    }
}

?>