<?php

namespace App\Forum\Pages;

use App\Classes\Template;
use App\Classes\Database;
use App\Classes\PageBase;
use App\Interfaces\PageInterface;

class ProfilePage extends PageBase implements PageInterface
{
    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'User profile ';
    }

    private function UrlHandler()
    {

    }

    public function Do()
    {
        $this->template = new Template('profile', 'profile');

        parent::RenderPage('profile');
    }
}

?>