<?php

require_once './app/template/template.php';

require_once './app/forum/controllers/account.php';

require_once './app/helpers/session.php';
require_once './app/helpers/url_manager.php';

class HeaderWidget
{
    public $Template;
    private string $themePath;

    public function __construct(string $theme)
    {
        $this->themePath = './themes/' . $theme;
        $this->Template = new Template($this->themePath . '/templates/header/header.html');
        $this->Do();
    }

    private function Do()
    {
        $ServerPath = UrlManager::GetPath();
        $menuTemplate = new Template($this->themePath . '/templates/header/nav/menu.html');

        $this->Template->AddEntry('{menu}', $menuTemplate->templ);
        $this->Template->AddEntry('{server_url}', $ServerPath);

        if (Session::IsLogged())
        {
            $userTemplate = new Template($this->themePath . '/templates/header/nav/user.html');
            $userTemplate->AddEntry('{avatar}', $_SESSION['account']['avatar']);
            $userTemplate->AddEntry('{username}', $_SESSION['account']['username']);
            $userTemplate->Replace();

            $this->Template->AddEntry('{user}', $userTemplate->templ);
            $this->Template->AddEntry('{login_register}', '');
        }
        else
        {
            $loginRegisterTemplate = new Template($this->themePath . '/templates/header/nav/login_register.html');
            $loginRegisterTemplate->AddEntry('{server_url}', $ServerPath);
            $loginRegisterTemplate->Replace();
            
            $this->Template->AddEntry('{user}', '');
            $this->Template->AddEntry('{login_register}', $loginRegisterTemplate->templ);
        }

        $this->Template->Replace();
    }
}

?>