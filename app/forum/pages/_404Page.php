<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Template;
use App\Interfaces\PageInterface;

class _404Page extends PageBase implements PageInterface
{
    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->forumDesc = 'Bit has not been found!';
    }

    public function Do()
    {
        $this->template = new Template('./themes/' . $this->theme . '/templates/404/404.html');
        $this->template->AddEntry('{server_url}', $this->serverPath);
        parent::RenderPage('/templates/404/styles.html');
    }
}

?>