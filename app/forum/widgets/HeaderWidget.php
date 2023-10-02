<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\SessionManager;
use App\Classes\AvatarUtils;
use App\Classes\Permissions;

/**
 * HeaderWidget represents a widget for rendering the header section of a forum page.
 */
class HeaderWidget
{
    /**
     * @var mixed The HTML template for the header section.
     */
    public $template;

    /**
     * Constructs a new HeaderWidget instance.
     */
    public function __construct()
    {
        $this->template = new Template('header', 'header');
        $this->Do();
    }

    /**
     * Perform the rendering logic for the header.
     */
    private function Do()
    {
        $menuTemplate = new Template('header/nav', 'menu');

        $this->template->AddEntry('{menu}', $menuTemplate->template);

        if (SessionManager::IsLogged())
            $this->RenderLoggedUser();
        else
            $this->RenderGuest();

        $this->template->Replace();
        $this->template = $this->template->template;
    }

    /**
     * Render the content for a logged-in user.
     */
    private function RenderLoggedUser()
    {
        $userOptions = '';

        $userTemplate = new Template('header/nav', 'user');
        $userTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($_SESSION['bitboard_user']->avatar));
        $userTemplate->AddEntry('{username}', $_SESSION['bitboard_user']->username);
        $userTemplate->Replace();

        $userMenuTemplate = new Template('header/nav', 'user_menu');

        if($_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
        {
            $userOptions = new Template('header/nav', 'user_menu_admin_panel');
            $userOptions = $userOptions->template;
        }

        $this->template->AddEntry('{user}', $userTemplate->template);
        $this->template->AddEntry('{login_register}', '');
        $this->template->AddEntry('{user_menu}', $userMenuTemplate->template);
        $this->template->AddEntry('{user_options}', $userOptions);
    }

    /**
     * Render the content for a guest user.
     */
    private function RenderGuest()
    {
        $loginRegisterTemplate = new Template('header/nav', 'login_register');
            
        $this->template->AddEntry('{user}', '');
        $this->template->AddEntry('{login_register}', $loginRegisterTemplate->template);
    }
}

?>