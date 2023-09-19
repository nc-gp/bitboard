<?php

require_once './app/template/template.php';
require_once './app/helpers/url_manager.php';

class StylesWidget
{
    public $Template;

    public function __construct(string $theme, string $templatePath)
    {
        $this->Template = new Template('./themes/' . $theme . $templatePath);
        $this->Do();
    }

    private function Do()
    {
        $this->Template->AddEntry('{server_url}', UrlManager::GetPath());
        $this->Template->Replace();
    }
}

?>