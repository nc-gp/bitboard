<?php

namespace App\Forum\Pages;

use Exception;

use App\Classes\Database;
use App\Classes\EmailUtils;
use App\Classes\PageBase;
use App\Classes\PasswordUtils;
use App\Classes\SessionManager;
use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\UsernameUtils;

use App\Forum\Controllers\AccountController;
use App\Forum\Widgets\NotifyWidget;

use App\Interfaces\PageInterface;

class RegisterPage extends PageBase implements PageInterface
{
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Register';
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath);

        if (!empty($this->forumData->actionParameters[1]))
            UrlManager::Redirect($this->serverPath . 'register');

        if (!isset($_POST['username']))
            return;

        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
            $this->Fail('Invalid request. Please try again.');

        $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $passwordConfirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        if (!UsernameUtils::Validate($username) || mb_strlen($username) > 32)
            $this->Fail('Username must be between 4 and 32 characters.');

        if (!EmailUtils::Validate($email) || mb_strlen($email) > 64)
            $this->Fail('Please enter a valid email address.');

        if (!PasswordUtils::IsValid($password))
            $this->Fail('Password must be at least 8 characters and contain a number, uppercase and lowercase letter.');

        if ($password !== $passwordConfirm)
            $this->Fail('Passwords do not match.');

        $existing = $this->database->Query('SELECT id FROM bit_accounts WHERE username = ? OR email = ? LIMIT 1', $username, $email)->FetchArray();
        if (!empty($existing))
            $this->Fail('That username or email is already registered.');

        $rank = $this->database->Query('SELECT id, rank_flags FROM bit_ranks WHERE rank_name = ? LIMIT 1', 'User')->FetchArray();
        if (empty($rank))
            $this->Fail('Registration is currently unavailable.');

        try
        {
            AccountController::Create(
                $this->database,
                $username,
                PasswordUtils::GetHash($password),
                $email,
                (int) $rank['id']
            );

            $account = AccountController::GetAccountByLogin($this->database, $username);
            if (empty($account))
                throw new Exception('The new account could not be loaded.');

            $account['permissions'] = $rank['rank_flags'];
            SessionManager::Login($account);
        }
        catch (Exception $e)
        {
            error_log('BitBoard registration failed: ' . $e->getMessage());
            $this->Fail('Unable to create the account. Please try again.');
        }

        UrlManager::Redirect($this->serverPath);
    }

    private function Fail(string $message): void
    {
        SessionManager::AddInformation('register', $message, true);
        UrlManager::Redirect($this->serverPath . 'register');
    }

    private function CheckError(): void
    {
        if (!isset($_SESSION['bb-info-register']['msg']))
            return;

        $message = $_SESSION['bb-info-register']['msg'];
        $rgb = $_SESSION['bb-info-register']['rgb'] ?? '255, 53, 53';
        $notify = new NotifyWidget($message, $rgb);
        $this->error = $notify->template;
        SessionManager::RemoveInformation('register');
    }

    public function Do(): void
    {
        $this->CheckError();

        $this->template = new Template('register', 'register');
        $this->template->AddEscapedEntry('{forum_name}', $this->forumName);
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('register');
    }
}

?>
