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
use App\Forum\Controllers\RegistrationController;
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

        $honeypot = isset($_POST['bb_website']) && is_string($_POST['bb_website']) ? trim($_POST['bb_website']) : '';
        $nonce = isset($_POST['form_nonce']) && is_string($_POST['form_nonce']) ? $_POST['form_nonce'] : '';
        $expectedNonce = $_SESSION['bb_register_nonce'] ?? '';
        $startedAt = isset($_SESSION['bb_register_started']) ? (int) $_SESSION['bb_register_started'] : 0;

        unset($_SESSION['bb_register_nonce'], $_SESSION['bb_register_started']);

        if ($honeypot !== '')
            $this->Fail('Registration could not be completed. Please try again.');

        if (!is_string($expectedNonce) || $expectedNonce === '' || !hash_equals($expectedNonce, $nonce))
            $this->Fail('Your registration form expired. Please reopen the page and try again.');

        $minimumDelay = max(0, min(60, (int) ($this->forumData->registration_min_delay ?? 3)));

        if ($minimumDelay > 0 && (time() - $startedAt) < $minimumDelay)
            $this->Fail('Please take a moment to complete the form and try again.');

        $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : '';
        $ipLimit = max(1, min(100, (int) ($this->forumData->registration_ip_limit ?? 5)));

        if (RegistrationController::IsIpBlocked($this->database, $ipAddress, $ipLimit))
            $this->Fail('Too many registration attempts from your network. Please try again later.');

        RegistrationController::RecordAttempt($this->database, $ipAddress);

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

        if (!isset($_POST['terms']))
            $this->Fail('You must accept the registration terms to continue.');

        $emailMode = (string) ($this->forumData->registration_email_mode ?? 'off');

        if (!array_key_exists($emailMode, RegistrationController::EMAIL_MODES))
            $emailMode = 'off';

        if (!RegistrationController::EmailAllowed(
            $email,
            $emailMode,
            (string) ($this->forumData->registration_email_domains ?? '')
        ))
            $this->Fail('That email domain is not allowed for registration.');

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
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        {
            $_SESSION['bb_register_nonce'] = bin2hex(random_bytes(16));
            $_SESSION['bb_register_started'] = time();
        }

        $this->CheckError();

        $terms = trim((string) ($this->forumData->registration_terms ?? ''));

        if ($terms === '')
            $terms = 'By registering, you agree to follow the forum rules and keep your account secure.';

        $this->template = new Template('register', 'register');
        $this->template->AddEscapedEntry('{forum_name}', $this->forumName);
        $this->template->AddEscapedEntry('{form_nonce}', $_SESSION['bb_register_nonce'] ?? '');
        $this->template->AddEscapedEntry('{terms_text}', $terms);
        $this->template->AddEntry('{error}', $this->error);

        parent::RenderPage('register');
    }
}

?>
