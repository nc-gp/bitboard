<?php

namespace App\Install\Steps;

use App\Classes\SessionManager;
use App\Classes\Template;
use App\Classes\Install\StepBase;
use App\Interfaces\Install\StepInterface;

use App\Classes\File;

use App\Classes\Database;
use App\Forum\Controllers\CategoryController;
use App\Forum\Controllers\ForumController;
use App\Forum\Controllers\AccountController;
use App\Forum\Controllers\ThreadController;
use App\Forum\Controllers\RankController;
use App\Classes\Permissions;
use App\Forum\Controllers\PostController;
use App\Forum\Controllers\SubForumController;
use Exception;

class Step_3 extends StepBase implements StepInterface
{
	private static string $error = '';

	public static function Execute()
	{
		self::$step = 3;
		self::$template = new Template('./app/install/templates/', '3', true);

		if(isset($_SESSION['bb-info-mysql']))
		{
			$err = new Template('./app/install/templates/other/', 'error', true);
			$err->AddEntry('{error}', $_SESSION['bb-info-mysql']['msg']);
			$err->Replace();

			self::$error = $err->template;

			SessionManager::RemoveInformation('mysql');
		}

		self::$template->AddEntry("{error}", self::$error);

		parent::RenderPage();
	}

	public static function Handler()
	{
		if(strlen($_POST['host']) <= 0)
		{
			SessionManager::AddInformation('mysql', 'Host can\'t be empty!', true);
			return;
		}

		if(strlen($_POST['name']) <= 0)
		{
			SessionManager::AddInformation('mysql', 'Database name can\'t be empty!', true);
			return;
		}

		if(strlen($_POST['username']) <= 0)
		{
			SessionManager::AddInformation('mysql', 'Username can\'t be empty!', true);
			return;
		}

		$config = array(
			'host' => $_POST['host'],
			'user' => $_POST['username'],
			'pass' => $_POST['password'],
			'name' => $_POST['name']
		);

		try 
		{
			$mysqli = mysqli_connect($config['host'], $config['user'], $config['pass']);
			if(mysqli_connect_errno())
			{
				SessionManager::AddInformation('mysql', mysqli_connect_error(), true);
				return;
			}

			if(!mysqli_query($mysqli, 'CREATE DATABASE IF NOT EXISTS ' . $config['name']))
			{
				SessionManager::AddInformation('mysql', mysqli_error($mysqli), true);
				return;
			}
		} 
		catch (Exception $e) 
		{
			SessionManager::AddInformation('mysql', $e->getMessage(), true);
			return;
		}

		$configFile = new File('./app/config.php');
		$data = '<?php' . "\n\n";
		$data .= '$config["host"] = \'' . $config['host'] . '\';' . "\n";
		$data .= '$config["user"] = \'' . $config['user'] . '\';' . "\n";
		$data .= '$config["pass"] = \'' . $config['pass'] . '\';' . "\n";
		$data .= '$config["name"] = \'' . $config['name'] . '\';' . "\n\n?>";
		$configFile->UpdateData($data);
		$configFile->Save();

		$InstallDatabase = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

		$InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_settings (
			id TINYINT(1) UNSIGNED PRIMARY KEY,
			forum_name VARCHAR(64) NOT NULL,
			forum_description VARCHAR(256) NOT NULL,
			forum_online TINYINT(1) NOT NULL,
			forum_online_msg VARCHAR(1024) NOT NULL,
			forum_theme VARCHAR(64) NOT NULL
		)');

		$InstallDatabase->Query('INSERT INTO bit_settings 
			(id,forum_name,forum_description,forum_online,forum_online_msg,forum_theme) 
			VALUES (?,?,?,?,?,?,?)', 
			array(0, 'Forum', 'Your awesome forum', 1, 'Back soon.', 'default')
		);

		CategoryController::CreateTable($InstallDatabase);
		CategoryController::Create($InstallDatabase, 'Global', 'Everything goes here', 'world');

		ForumController::CreateTable($InstallDatabase);
		ForumController::Create($InstallDatabase, 1, 'Welcomes', 'Everyone says hello!', 'hand-stop', 1);

		SubForumController::CreateTable($InstallDatabase);

		AccountController::CreateTable($InstallDatabase);
		AccountController::Create($InstallDatabase, 'BitBot', '-', '-', 1);

		ThreadController::CreateTable($InstallDatabase);
		ThreadController::Create($InstallDatabase, 1, 'Hi!', '<h2>Welcome to Bitboard!</h2><br><p>Bitboard has been successfully installed.</p><br><p>If you have any questions or need assistance, feel free to explore our issues section on github. We\'re here to make your experience with BitBoard as smooth as possible.</p><br><br><p>Thank you for choosing BitBoard. We hope you enjoy using our app to its fullest!</p>', true, false, 1);

		PostController::CreateTable($InstallDatabase);

		RankController::CreateTable($InstallDatabase);
		RankController::Create($InstallDatabase, 'Admin', '<span style="color: #B22222">{username}</span>', Permissions::$All);
		RankController::Create($InstallDatabase, 'User', '<span style="font-weight: bold">{username}</span>', Permissions::VIEWING_FORUM | Permissions::CREATING_POSTS | Permissions::CREATING_THREADS);
		RankController::Create($InstallDatabase, 'Banned', '<span style="text-decoration: line-through">{username}</span>', 0);

		parent::Handler();
	}
}

?>