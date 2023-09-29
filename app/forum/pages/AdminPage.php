<?php

namespace App\Forum\Pages;

use App\Classes\Console;
use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\UrlManager;
use App\Interfaces\PageInterface;
use App\Classes\Template;
use App\Forum\Controllers\RankController;

class AdminPage extends PageBase implements PageInterface
{
    private $content = '';

    public function __construct(Database $db, array $forumData)
    {
        parent::__construct($db, $forumData);
        $this->UrlHandler();
        $this->forumDesc = 'Admin panel';
    }

    private function UrlHandler()
    {
        if(!$_SESSION['bitboard_user']->HasPermission(Permissions::ADMIN_PANEL_ACCESS))
        {
            UrlManager::Redirect($this->serverPath);
            return;
        }

        Console::Log($this->forumData);

        $option = isset($this->forumData['actionParameters'][1]) ? $this->forumData['actionParameters'][1] : 0;

        switch($option)
        {
            case 'settings':
            {
                if($this->forumData['actionParameters'][2]) // isset process
                {

                }

                $this->content = new Template('admin/main', 'settings');
                $this->content->AddEntry('{forum_name}', $this->forumData['forum_name']);
                $this->content->AddEntry('{forum_desc}', $this->forumData['forum_description']);
                $this->content->AddEntry('{forum_online}', $this->forumData['forum_online'] ? 'checked' : '');
                $this->content->AddEntry('{forum_online_msg}', $this->forumData['forum_online_msg']);
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
        $this->template = new Template('admin', 'admin');
        $this->template->AddEntry('{content}', $this->content);

        parent::RenderPage('admin');
    }
}

?>