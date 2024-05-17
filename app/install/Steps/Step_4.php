<?php

namespace App\Install\Steps;

use App\Classes\SessionManager;
use App\Classes\Template;
use App\Classes\Install\StepBase;
use App\Interfaces\Install\StepInterface;

use App\Classes\UsernameUtils;
use App\Classes\EmailUtils;
use App\Classes\PasswordUtils;

use App\Forum\Controllers\AccountController;
use App\Classes\Database;
use Exception;

class Step_4 extends StepBase implements StepInterface
{
	private static string $error = '';

	public static function Execute()
	{
		self::$step = 4;
		self::$template = new Template('./app/install/templates/', '4', true);

		if(isset($_SESSION['bb-info-account']))
		{
			$err = new Template('./app/install/templates/other/', 'error', true);
			$err->AddEntry('{error}', $_SESSION['bb-info-account']['msg']);
			$err->Replace();

			self::$error = $err->template;

			SessionManager::RemoveInformation('account');
		}

		self::$template->AddEntry('{error}', self::$error);

		parent::RenderPage();
	}

    public static function Handler()
	{
		$username = isset($_POST['username']) ? $_POST['username'] : '';

		if(!UsernameUtils::Validate($username))
		{
			SessionManager::AddInformation('account', 'Username is too short! ( 4 >= )', true);
			return;
		}

		$email = isset($_POST['email']) ? $_POST['email'] : '';

		if(!EmailUtils::Validate($email))
		{
			SessionManager::AddInformation('account', 'Email is not valid. Try another one.', true);
			return;
		}

		$password = isset($_POST['password']) ? $_POST['password'] : '';

		if (!PasswordUtils::CheckLength($password))
		{
			SessionManager::AddInformation('account', 'Password is too short! ( 8 >= )', true);
			return;
		}

		if (!PasswordUtils::CheckNumber($password))
		{
			SessionManager::AddInformation('account', 'Password needs to have atleast one number!', true);
			return;
		}

		if (!PasswordUtils::CheckLetter($password))
		{
			SessionManager::AddInformation('account', 'Password needs to have atleast one character!', true);
			return;
		}

		require_once './app/config.php';

		try {
			$NewUserDatabase = new Database($config['host'], $config['user'], $config['pass']);
			$NewUserDatabase->SelectDatabase($config['name']);

			AccountController::Create(
				$NewUserDatabase,
				$_POST['username'],
				PasswordUtils::GetHash($_POST['password']),
				$_POST['email'],
				1
			);

			$NewUserDatabase->Close();
		}
		catch (Exception $e) {
			SessionManager::AddInformation('account', $e->getMessage(), true);
			return;
		}

		parent::Handler();
	}
}

?>