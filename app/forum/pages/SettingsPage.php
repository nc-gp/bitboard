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

use App\Forum\Widgets\NotifyWidget;

use App\Interfaces\PageInterface;

class SettingsPage extends PageBase implements PageInterface
{
    private string $error = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'Account settings';
        $this->UrlHandler();
    }

    private function UrlHandler(): void
    {
        if (!SessionManager::IsLogged() || !isset($_SESSION['bitboard_user']) || !is_object($_SESSION['bitboard_user']))
            UrlManager::Redirect($this->serverPath . 'login');

        if (!empty($this->forumData->actionParameters[1]))
            UrlManager::Redirect($this->serverPath . 'settings');

        if (!isset($_POST['email']))
            return;

        if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? ''))
            $this->Fail('Invalid request. Please try again.');

        $userId = (int) $_SESSION['bitboard_user']->id;
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $bio = isset($_POST['bio']) && is_string($_POST['bio']) ? trim($_POST['bio']) : '';
        $signature = isset($_POST['signature']) && is_string($_POST['signature']) ? trim($_POST['signature']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $passwordConfirm = isset($_POST['password_confirm']) && is_string($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        if (!EmailUtils::Validate($email) || mb_strlen($email) > 64)
            $this->Fail('Please enter a valid email address.');

        if (mb_strlen($bio) > 1000)
            $this->Fail('Bio must be 1000 characters or fewer.');

        if (mb_strlen($signature) > 500)
            $this->Fail('Signature must be 500 characters or fewer.');

        $existing = $this->database->Query('SELECT id FROM bit_accounts WHERE email = ? AND id <> ? LIMIT 1', $email, $userId)->FetchArray();
        if (!empty($existing))
            $this->Fail('That email address is already in use.');

        if ($password !== '' || $passwordConfirm !== '')
        {
            if ($password !== $passwordConfirm)
                $this->Fail('Passwords do not match.');

            if (!PasswordUtils::IsValid($password))
                $this->Fail('Password must be at least 8 characters and contain a number, uppercase and lowercase letter.');
        }

        try
        {
            if ($password === '')
            {
                $this->database->Query('UPDATE bit_accounts SET email = ?, bio = ?, signature = ? WHERE id = ?',
                    $email, $bio, $signature, $userId);
            }
            else
            {
                $this->database->Query('UPDATE bit_accounts SET email = ?, bio = ?, signature = ?, pass = ? WHERE id = ?',
                    $email, $bio, $signature, PasswordUtils::GetHash($password), $userId);
            }

            $_SESSION['bitboard_user']->Update($this->database);
        }
        catch (Exception $e)
        {
            error_log('BitBoard account update failed: ' . $e->getMessage());
            $this->Fail('Unable to update the account. Please try again.');
        }

        SessionManager::AddInformation('account', 'Your account has been updated.', true, '53, 255, 53');
        UrlManager::Redirect($this->serverPath . 'settings');
    }

    private function Fail(string $message): void
    {
        SessionManager::AddInformation('account', $message, true);
        UrlManager::Redirect($this->serverPath . 'settings');
    }

    private function CheckError(): void
    {
        if (!isset($_SESSION['bb-info-account']['msg']))
            return;

        $message = $_SESSION['bb-info-account']['msg'];
        $rgb = $_SESSION['bb-info-account']['rgb'] ?? '255, 53, 53';
        $notify = new NotifyWidget($message, $rgb);
        $this->error = $notify->template;
        SessionManager::RemoveInformation('account');
    }

    public function Do(): void
    {
        $this->CheckError();

        $this->template = new Template('settings', 'settings');
        $this->template->AddEscapedEntry('{username}', $_SESSION['bitboard_user']->username);
        $this->template->AddEscapedEntry('{email}', $_SESSION['bitboard_user']->email);
        $this->template->AddEscapedEntry('{bio}', $_SESSION['bitboard_user']->bio ?? '');
        $this->template->AddEscapedEntry('{signature}', $_SESSION['bitboard_user']->signature ?? '');
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('settings');
    }
}

?>
