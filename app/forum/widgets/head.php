<?php

require_once './app/template/template.php';
require_once './app/helpers/url_manager.php';

class HeadWidget
{
    public $Template;
    private string $themePath;
    private string $forumName;
    private string $forumDesc;
    private string $stylesTemplate;

    public function __construct(string $theme, string $forumName, string $forumDesc, string $stylesTemplate = '')
    {
        $this->themePath = './themes/' . $theme;
        $this->forumName = $forumName;
        $this->forumDesc = $forumDesc;
        $this->stylesTemplate = $stylesTemplate;
        $this->Template = new Template($this->themePath . '/templates/head/head.html');
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