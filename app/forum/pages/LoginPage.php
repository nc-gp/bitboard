<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\Template;
use App\Classes\PageBase;
use App\Classes\SessionManager;
use App\Classes\PasswordUtils;
use App\Classes\UrlManager;
use App\Forum\Controllers\AccountController;
use App\Interfaces\PageInterface;

class LoginPage extends PageBase implements PageInterface
{
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Login';
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if(SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath);

        if(!empty($this->forumData->actionParameters[1]) && $this->forumData->actionParameters[1] !== 'process')
            UrlManager::Redirect($this->serverPath . 'login');

        if(!isset($_POST['username']))
            return;

        $account = AccountController::GetAccountByName($this->database, $_POST['username']);
        if(empty($account))
        {
            SessionManager::AddInformation('login', 'Account with that username doesnt exists.', true);
            UrlManager::Redirect($this->serverPath . 'login');
        }

        $permissions = $this->database->Query('SELECT rank_flags FROM bit_ranks WHERE id = ?', $account['rank_id'])->FetchArray();
        $account['permissions'] = $permissions['rank_flags'];

        if(!PasswordUtils::Verify($_POST['password'], $account['pass']))
        {
            SessionManager::AddInformation('login', 'Invalid password', true);
            UrlManager::Redirect($this->serverPath . 'login');
        }

        SessionManager::Set($account);
        UrlManager::Redirect($this->serverPath);
    }

    private function CheckError()
    {
        if(!isset($_SESSION['bb-info-login']['msg']))
            return;
        
        $errorTemplate = new Template('login', 'error');
        $errorTemplate->AddEntry('{error}', $_SESSION['bb-info-login']['msg']);
        $errorTemplate->Replace();

        $this->error = $errorTemplate->template;

        SessionManager::RemoveInformation('login');
    }

    public function Do()
    {
        $this->CheckError();

        $this->template = new Template('login', 'login');
        $this->template->AddEntry('{forum_name}', $this->forumName);
        $this->template->AddEntry('{register_url}', $this->serverPath . 'register');
        $this->template->AddEntry('{error}', $this->error);
        
        parent::RenderPage('login');
    }
}

?>