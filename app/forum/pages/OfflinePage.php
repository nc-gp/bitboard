<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\Template;
use App\Classes\PageBase;
use App\Classes\SessionManager;
use App\Classes\UrlManager;

use App\Interfaces\PageInterface;

class OfflinePage extends PageBase implements PageInterface
{
    private string $message;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->message = trim((string) $data->forum_online_msg);
        $this->forumDesc = $this->message !== '' ? $this->message : 'The forum is temporarily offline.';
        $this->ogTitle = 'Forum offline - ' . $this->forumName;
        $this->ogDescription = $this->forumDesc;
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if ($this->forumData->forum_online)
            UrlManager::Redirect($this->serverPath);

        if ($this->forumData->actionParameters[0] !== 'offline' || !empty($this->forumData->actionParameters[1]))
            UrlManager::Redirect($this->serverPath . 'offline');
    }

    private function BuildActionLink(string $url, string $icon, string $label): string
    {
        $template = new Template('offline', 'action');
        $template->AddEscapedEntry('{url}', $url);
        $template->AddEscapedEntry('{icon}', $icon);
        $template->AddEscapedEntry('{label}', $label);
        $template->Replace();

        return $template->template;
    }

    private function BuildAction(): string
    {
        if (!SessionManager::IsLogged())
            return $this->BuildActionLink($this->serverPath . 'login', 'login', 'Staff login');

        if (SessionManager::IsStaff())
            return $this->BuildActionLink($this->serverPath, 'home', 'View forum');

        return $this->BuildActionLink($this->serverPath . 'logout', 'logout', 'Log out');
    }

    public function Do()
    {
        $this->template = new Template('offline', 'main');
        $this->template->AddEscapedEntry('{forum_name}', $this->forumName);
        $this->template->AddEscapedEntry('{reason}', $this->message !== '' ? $this->message : 'We are making some changes and will be back shortly. Please check back soon.');
        $this->template->AddEntry('{offline_action}', $this->BuildAction());

        parent::RenderPage('offline');
    }
}

?>