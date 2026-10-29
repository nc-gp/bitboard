<?php

namespace App\Forum\Pages;

use Exception;

use App\Classes\Database;
use App\Classes\EmailUtils;
use App\Classes\AvatarUtils;
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

        $avatarName = null;
        $oldAvatar = (string) ($_SESSION['bitboard_user']->avatar ?? 'default.webp');
        $removeAvatar = isset($_POST['remove_avatar']);
        $file = $_FILES['avatar'] ?? null;

        if (is_array($file) && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE)
        {
            if ($file['error'] !== UPLOAD_ERR_OK)
                $this->Fail('Unable to upload the avatar. Please try again.');

            if (!isset($file['tmp_name']) || !is_string($file['tmp_name']))
                $this->Fail('Unable to upload the avatar. Please try again.');

            if (!is_uploaded_file($file['tmp_name']))
                $this->Fail('Unable to upload the avatar. Please try again.');

            if ((int) $file['size'] <= 0 || (int) $file['size'] > 2 * 1024 * 1024)
                $this->Fail('Avatar must be smaller than 2 MB.');

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            if (!is_string($mime) || !isset($allowed[$mime]))
                $this->Fail('Avatar must be a JPEG, PNG, WebP, or GIF image.');

            if (@getimagesize($file['tmp_name']) === false)
                $this->Fail('Avatar must be a valid image.');

            $directory = __DIR__ . '/../../../assets/images/avatars';

            if (!is_dir($directory) && !@mkdir($directory, 0775, true))
                $this->Fail('Unable to store the avatar. Please try again.');

            $avatarName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $avatarName))
                $this->Fail('Unable to store the avatar. Please try again.');
        }
        elseif ($removeAvatar)
        {
            $avatarName = 'default.webp';
        }

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

            if ($avatarName !== null)
            {
                $this->database->Query('UPDATE bit_accounts SET avatar = ? WHERE id = ?', $avatarName, $userId);

                if ($oldAvatar !== 'default.webp' && $oldAvatar !== '' && $oldAvatar !== $avatarName)
                {
                    $oldPath = __DIR__ . '/../../../assets/images/avatars/' . basename($oldAvatar);

                    if (is_file($oldPath))
                        @unlink($oldPath);
                }
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
        $this->template->AddEntry('{avatar}', AvatarUtils::GetPath((string) ($_SESSION['bitboard_user']->avatar ?? 'default.webp')));
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('settings');
    }
}

?>
