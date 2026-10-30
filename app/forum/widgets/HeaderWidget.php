<?php

namespace App\Forum\Widgets;

use App\Classes\Database;
use App\Classes\Template;
use App\Classes\SessionManager;
use App\Classes\AvatarUtils;
use App\Classes\Permissions;
use App\Classes\UrlManager;
use App\Classes\AdminPath;

use App\Forum\Controllers\NotificationController;
use App\Forum\Controllers\ReportController;

use Throwable;

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
     * @var string The optional staff-only offline notice.
     */
    private string $offlineNotice = '';

    private Database $database;

    /**
     * Constructs a new HeaderWidget instance.
     *
     * @param Database database       The database instance.
     * @param string   offlineNotice  Optional staff-only offline notice.
     */
    public function __construct(Database $database, string $offlineNotice = '')
    {
        $this->database = $database;
        $this->offlineNotice = $offlineNotice;
        $this->template = new Template('header', 'header');
        $this->Do();
    }

    /**
     * Perform the rendering logic for the header.
     */
    private function Do()
    {
        $menuTemplate = new Template('header/nav', 'menu');

        $offlineNotice = '';
        if ($this->offlineNotice !== '')
        {
            $noticeTemplate = new Template('header', 'offline_notice');
            $noticeTemplate->AddEscapedEntry('{message}', $this->offlineNotice);
            $noticeTemplate->Replace();
            $offlineNotice = $noticeTemplate->template;
        }

        $this->template->AddEntry('{offline_notice}', $offlineNotice);
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
        $userTemplate->AddEscapedEntry('{username}', $_SESSION['bitboard_user']->username);
        $userTemplate->AddEntry('{notification_bell}', $this->BuildNotificationBell());
        $userTemplate->Replace();

        $userMenuTemplate = new Template('header/nav', 'user_menu');

        $reportsOption = '';
        if ($_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_REPORTS))
        {
            try
            {
                $openReports = ReportController::CountOpen($this->database);
            }
            catch (Throwable $e)
            {
                error_log('BitBoard report count failed: ' . $e->getMessage());
                $openReports = 0;
            }

            $label = 'Reports' . ($openReports > 0 ? ' (' . $openReports . ')' : '');
            $reportsOption = '<li><a href="' . Template::Escape(UrlManager::GetPath() . 'reports/page/1') . '">'
                . Template::Escape($label) . '</a></li>';
        }

        if($_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
        {
            $userOptions = new Template('header/nav', 'user_menu_admin_panel');
            $userOptions->AddEscapedEntry('{admin_url}', UrlManager::GetPath() . AdminPath::FromDatabase($this->database));
            $userOptions->Replace();
            $userOptions = $userOptions->template;
        }

        $this->template->AddEntry('{user}', $userTemplate->template);
        $this->template->AddEntry('{login_register}', '');
        $this->template->AddEntry('{user_menu}', $userMenuTemplate->template);
        $this->template->AddEntry('{reports_option}', $reportsOption);
        $this->template->AddEntry('{user_options}', $userOptions);
    }

    /**
     * Render the notification bell with the unread count.
     */
    private function BuildNotificationBell(): string
    {
        try
        {
            $userID = (int) $_SESSION['bitboard_user']->id;
            $unread = NotificationController::CountUnread($this->database, $userID);
        }
        catch (Throwable $e)
        {
            // A notification failure must not take down every forum page.
            error_log('BitBoard notification count failed: ' . $e->getMessage());
            $unread = 0;
        }

        $template = new Template('header/nav', 'notifications');
        $template->AddEscapedEntry('{label}', $unread > 0
            ? 'Notifications (' . $unread . ' unread)'
            : 'Notifications');
        $template->AddEscapedEntry('{badge_count}', $unread > 99 ? '99+' : $unread);
        $template->AddEntry('{badge_hidden}', $unread > 0 ? '' : ' hidden');
        $template->Replace();

        return $template->template;
    }

    /**
     * Render the content for a guest user.
     */
    private function RenderGuest()
    {
        $loginRegisterTemplate = new Template('header/nav', 'login_register');
            
        $this->template->AddEntry('{user}', '');
        $this->template->AddEntry('{notification_bell}', '');
        $this->template->AddEntry('{login_register}', $loginRegisterTemplate->template);
    }
}

?>
