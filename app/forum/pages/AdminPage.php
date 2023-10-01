<?php

namespace App\Forum\Pages;

use App\Classes\Console;
use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Interfaces\PageInterface;
use App\Classes\Template;
use App\Forum\Controllers\RankController;

use App\Forum\Widgets\NotifyWidget;

class AdminPage extends PageBase implements PageInterface
{
    private $content = '';
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
        $this->forumDesc = 'Admin panel';
    }

    private function CheckError()
    {
        if(!isset($_SESSION['bb-info-settings']))
            return;

        $errorTemplate = new NotifyWidget($_SESSION['bb-info-settings']['msg'], $_SESSION['bb-info-settings']['rgb']);
        $this->error = $errorTemplate->template;

        SessionManager::RemoveInformation('settings');
    }

    private function UrlHandler()
    {
        if(!SessionManager::IsLogged() || !$_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
        {
            UrlManager::Redirect($this->serverPath);
            return;
        }

        Console::Log($this->forumData);

        $option = isset($this->forumData->actionParameters[1]) ? $this->forumData->actionParameters[1] : 0;

        switch($option)
        {
            case 'settings':
            {
                if(count($this->forumData->actionParameters) > 2)
                {
                    Console::Log($_POST);
                    $forumName = isset($_POST['forumname']) ? $_POST['forumname'] : '';
                    $forumDesc = isset($_POST['forumdesc']) ? $_POST['forumdesc'] : '';
                    $forumOnlineMsg = isset($_POST['forumonline']) ? $_POST['forumonline'] : '';
                    $forumOnline = isset($_POST['online']) ? 1 : 0;
                    $forumForceLogin = isset($_POST['forum_force_login']) ? 1 : 0;

                    if(strlen($forumName) <= 0 || strlen($forumDesc) <= 0 || strlen($forumOnlineMsg) <= 0)
                    {
                        SessionManager::AddInformation('settings', 'Fields cannot be empty!', true);
                        UrlManager::Redirect($this->serverPath . 'admin/settings');
                        return;
                    }

                    $this->database->Query('UPDATE bit_settings SET forum_name = ?, forum_description = ?, forum_online_msg = ?, forum_online = ?, forum_force_login = ? WHERE id = 0', "$forumName", "$forumDesc", "$forumOnlineMsg", $forumOnline, $forumForceLogin);

                    SessionManager::AddInformation('settings', 'Forum settings has been updated!', true, '53, 255, 53');
                    UrlManager::Redirect($this->serverPath . 'admin/settings');
                    return;
                }

                $this->content = new Template('admin/main', 'settings');
                $this->content->AddEntry('{forum_name}', $this->forumData->forum_name);
                $this->content->AddEntry('{forum_desc}', $this->forumData->forum_description);
                $this->content->AddEntry('{forum_online}', $this->forumData->forum_online ? 'checked' : '');
                $this->content->AddEntry('{forum_online_msg}', $this->forumData->forum_online_msg);
                $this->content->AddEntry('{forum_force_login}', $this->forumData->forum_force_login ? 'checked' : '');
                $this->content->Replace();
                break;
            }
            case 'prefixes':
            {
                $prefixes = 'empty';
                $data = $this->database->Query('SELECT * FROM bit_prefixes WHERE id > 2')->FetchAll();

                if(!empty($data))
                {
                    $prefixes = '';
                    $prefix = '';
                    foreach ($data as $prefix_data) 
                    {
                        $prefix = new Template('admin/main/other', 'prefix');
                        $prefix->AddEntry('{prefix_name}', $prefix_data['prefix_name']);
                        $prefix->AddEntry('{prefix_class}', $prefix_data['prefix_class']);
                        $prefix->AddEntry('{prefix_id}', $prefix_data['id']);
                        $prefix->Replace();
                        $prefixes .= $prefix->template;
                    }
                }

                $this->content = new Template('admin/main', 'prefixes');
                $this->content->AddEntry('{prefixes}', $prefixes);
                $this->content->Replace();
                break;
            }
            default:
            {
                $this->content = new Template('admin/main', 'home');
                $this->content->AddEntry('{username}', $_SESSION['bitboard_user']->name);
                $this->content->AddEntry('{rankname}', RankController::GetRankNameByID($this->database, $_SESSION['bitboard_user']->rank_id));
                $this->content->Replace();
                break;
            }
        }

        $this->content = $this->content->template;
    }

    public function Do()
    {
        $this->CheckError();
        $this->template = new Template('admin', 'admin');
        $this->template->AddEntry('{content}', $this->content);
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('admin');
    }
}

?>