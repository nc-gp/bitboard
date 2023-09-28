<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\Template;
use App\Classes\PageBase;
use App\Classes\UrlManager;

use App\Interfaces\PageInterface;

class OfflinePage extends PageBase implements PageInterface
{
    private string $message;

    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->message = $forumData['forum_online_msg'];
        $this->forumDesc = 'Forum offline [' . $this->message . ']';
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if ($this->forumData['forum_online'])
        {
            UrlManager::Redirect($this->serverPath);
            return;
        }

        if (($this->forumData['actionParameters'][0] !== 'offline' || count($this->forumData['actionParameters']) > 1))
        {
            UrlManager::Redirect($this->serverPath . 'offline');
            return;
        }
    }

    public function Do()
    {
        $this->template = new Template('offline', 'main');
        $this->template->AddEntry('{reason}', $this->message);
        
        parent::RenderPage('offline');
    }
}

?>