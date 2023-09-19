<?php

require_once './app/template/template.php';
require_once './app/helpers/url_manager.php';

class FooterWidget
{
    public $Template;
    private string $themePath;

    public function __construct(string $theme)
    {
        $this->themePath = './themes/' . $theme;
        $this->Template = new Template($this->themePath . '/templates/footer/footer.html');
        $this->Do();
    }

    private function Do()
    {
        $this->Template->AddEntry('{server_url}', UrlManager::GetPath());
        $this->Template->Replace();
        
        // TODO: Add this to forum settings if someone want to keep the credits do BitBoard, but they don't need to.
        $this->Template->templ .= '<div id="bb-foot" style="text-align: center; margin: 10px 0;">Powered by BitBoard</div>';
    }
}

?>