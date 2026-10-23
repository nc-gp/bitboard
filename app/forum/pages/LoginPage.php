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

        if(!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
        {
            SessionManager::AddInformation('login', 'Invalid request. Please try again.', true);
            UrlManager::Redirect($this->serverPath . 'login');
        }

        $login = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

        if($login === '' || $password === '')
        {
            SessionManager::AddInformation('login', 'Invalid username/email or password.', true);
            UrlManager::Redirect($this->serverPath . 'login');
        }

        $account = AccountController::GetAccountByLogin($this->database, $login);
        if(empty($account) || !PasswordUtils::Verify($password, $account['pass']))
        {
            SessionManager::AddInformation('login', 'Invalid username/email or password.', true);
            UrlManager::Redirect($this->serverPath . 'login');
        }

        $permissions = $this->database->Query('SELECT rank_flags FROM bit_ranks WHERE id = ?', $account['rank_id'])->FetchArray();
        $account['permissions'] = $permissions['rank_flags'];

        SessionManager::Login($account);
        UrlManager::Redirect($this->serverPath);
    }

    private function CheckError()
    {
        if(!isset($_SESSION['bb-info-login']['msg']))
            return;
        
        $errorTemplate = new Template('login', 'error');
        $errorTemplate->AddJsEntry('{error}', $_SESSION['bb-info-login']['msg']);
        $errorTemplate->Replace();

        $this->error = $errorTemplate->template;

        SessionManager::RemoveInformation('login');
    }

    public function Do()
    {
        $this->CheckError();

        $this->template = new Template('login', 'login');
        $this->template->AddEscapedEntry('{forum_name}', $this->forumName);
        $this->template->AddEntry('{register_url}', $this->serverPath . 'register');
        $this->template->AddEntry('{error}', $this->error);
        
        parent::RenderPage('login');
    }
}

?>