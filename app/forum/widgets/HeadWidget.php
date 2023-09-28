<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\UrlManager;

class HeadWidget
{
    public $Template;
    private string $forumName;
    private string $forumDesc;
    private string $stylesTemplate;

    public function __construct(string $forumName, string $forumDesc, string $stylesTemplate = '')
    {
        $this->forumName = $forumName;
        $this->forumDesc = $forumDesc;
        $this->stylesTemplate = $stylesTemplate;
        $this->Template = new Template('head', 'head');
        $this->Do();
    }

    private function Do()
    {
        $this->Template->AddEntry('{forum_name}', $this->forumName);
        $this->Template->AddEntry('{page_title}', $this->forumDesc);
        $this->Template->AddEntry('{server_url}', UrlManager::GetPath());
        $this->Template->AddEntry('{styles}', $this->stylesTemplate);
        $this->Template->Replace();
    }
}

?>