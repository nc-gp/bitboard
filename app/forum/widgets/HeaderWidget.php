<?php

namespace App\Forum\Widgets;

use App\BB;
use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\SessionManager;
use App\Classes\AvatarUtils;
use App\Classes\Permissions;

class HeaderWidget
{
    public $Template;
    private $theme;

    public function __construct()
    {
        $this->Template = new Template('header', 'header');
        $this->theme = BB::$Data['forum_theme'];
        $this->Do();
    }

    private function Do()
    {
        $ServerPath = UrlManager::GetPath();
        $menuTemplate = new Template('header/nav', 'menu');

        $this->Template->AddEntry('{menu}', $menuTemplate->template);
        $this->Template->AddEntry('{server_url}', $ServerPath);

        if (SessionManager::IsLogged())
        {
            $userOptions = '';

            $userTemplate = new Template('header/nav', 'user');
            $userTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($this->theme, $_SESSION['bitboard_user']->avatar));
            $userTemplate->AddEntry('{username}', $_SESSION['bitboard_user']->name);
            $userTemplate->Replace();

            $userMenuTemplate = new Template('header/nav', 'user_menu');
            $userMenuTemplate->AddEntry('{server_url}', $ServerPath);
            $userMenuTemplate->Replace();

            if($_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
            {
                $userOptions = new Template('header/nav', 'user_menu_admin_panel');
                $userOptions->AddEntry('{server_url}', $ServerPath);
                $userOptions->Replace();
                $userOptions = $userOptions->template;
            }

            $this->Template->AddEntry('{user}', $userTemplate->template);
            $this->Template->AddEntry('{login_register}', '');
            $this->Template->AddEntry('{user_menu}', $userMenuTemplate->template);
            $this->Template->AddEntry('{user_options}', $userOptions);
        }
        else
        {
            $loginRegisterTemplate = new Template('header/nav', 'login_register');
            $loginRegisterTemplate->AddEntry('{server_url}', $ServerPath);
            $loginRegisterTemplate->Replace();
            
            $this->Template->AddEntry('{user}', '');
            $this->Template->AddEntry('{login_register}', $loginRegisterTemplate->template);
        }

        $this->Template->Replace();
    }
}

?>