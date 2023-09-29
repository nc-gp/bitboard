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
        $this->template = new Template('404', '404');
        parent::RenderPage('404');
    }
}

?>