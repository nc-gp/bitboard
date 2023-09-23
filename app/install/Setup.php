<?php

namespace App\Install;

use App\Classes\Database;
use App\Classes\UrlManager;
use App\Classes\Template;
use App\Classes\PasswordUtils;

use App\Forum\Controllers\CategoryController;
use App\Forum\Controllers\ForumController;
use App\Forum\Controllers\AccountController;
use App\Forum\Controllers\RankController;
use App\Forum\Controllers\ThreadController;

use App\Classes\File;
use App\Classes\UsernameUtils;
use App\Classes\EmailUtils;

use App\Classes\Permissions;
use App\Classes\SessionManager;

use Exception;

class Setup
{
	private $file;
	private int $CurrentStep;
	private $Step;

	public function __construct()
	{
		$this->file = new File('./app/install/step');
		$this->CurrentStep = $this->file->data == -1 ? 1 : $this->file->data;

		if (isset($_GET['bb_next']))
		{
			if (isset($_POST['step_3']))
			{
				$config = array(
					'host' => $_POST['host'],
					'user' => $_POST['user'],
					'pass' => $_POST['pass'],
					'name' => $_POST['name']
				);

				try 
				{
					$mysqli = mysqli_connect($config['host'], $config['user'], $config['pass']);
					if(mysqli_connect_errno())
					{
						SessionManager::AddInformation('mysql', mysqli_connect_error(), true);
						UrlManager::Redirect('./');
						return;
					}

					if(!mysqli_query($mysqli, 'CREATE DATABASE IF NOT EXISTS ' . $config['name']))
					{
						SessionManager::AddInformation('mysql', mysqli_error($mysqli), true);
						UrlManager::Redirect('./');
						return;
					}
				} 
				catch (Exception $e) 
				{
					SessionManager::AddInformation('mysql', $e->getMessage(), true);
					UrlManager::Redirect('./');
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

				$bit_settings = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_settings (
					id TINYINT(1) UNSIGNED PRIMARY KEY,
					forum_name VARCHAR(64) NOT NULL,
					forum_description VARCHAR(256) NOT NULL,
					forum_online TINYINT(1) NOT NULL,
					forum_online_msg VARCHAR(1024) NOT NULL,
					forum_theme VARCHAR(64) NOT NULL,
					reputation_negative TINYINT(1) NOT NULL
				)');

				$InstallDatabase->Query('INSERT INTO bit_settings 
					(id,forum_name,forum_description,forum_online,forum_online_msg,forum_theme,reputation_negative) 
					VALUES (?,?,?,?,?,?,?)', 
					array(0, 'Forum', 'Your awesome forum', 1, 'Back soon.', 'default', 1)
				);

				$bit_categories = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_categories (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category_name VARCHAR(128) NOT NULL,
					category_desc VARCHAR(256) NOT NULL,
					category_icon VARCHAR(512) NOT NULL,
					category_position INT(6) NOT NULL
				)');

				CategoryController::Create($InstallDatabase, 'Global', 'Everything goes here', 'world');

				$bit_forums = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_forums (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					category_id INT(6) NOT NULL,
					forum_name VARCHAR(128) NOT NULL,
					forum_desc VARCHAR(1024) NOT NULL,
					forum_icon VARCHAR(512) NOT NULL,
					forum_position INT(6) NOT NULL,
					is_locked TINYINT(1) NOT NULL
				)');

				ForumController::Create($InstallDatabase, 1, 'Welcomes', 'Everyone says hello!', 'hand-stop', 1);

				$bit_subforums = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_subforums (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					forum_id INT(6) NOT NULL,
					subforum_name VARCHAR(128) NOT NULL,
					subforum_desc VARCHAR(256) NOT NULL,
					is_locked TINYINT(1) NOT NULL
				)');

				$bit_accounts = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_accounts (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					username VARCHAR(32) NOT NULL,
					pass VARCHAR(1024) NOT NULL,
					email VARCHAR(64) NOT NULL,
					avatar VARCHAR(255) NULL DEFAULT NULL,
					reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					reputation INT(6) NOT NULL DEFAULT 0,
					last_ip VARCHAR(64),
					last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					rank_id INT(6) NOT NULL
				)');

				AccountController::Create($InstallDatabase, 'Guest', '-', '-', 1);

				$bit_threads = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_threads (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					user_id INT(6) NOT NULL,
					thread_title VARCHAR(128) NOT NULL,
					thread_content VARCHAR(4096) NOT NULL,
					thread_likes INT(6) NOT NULL,
					thread_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					thread_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					forum_id INT(6) NOT NULL,
					subforum_id INT(6) NOT NULL,
					is_closed TINYINT(1) NOT NULL,
					is_pinned TINYINT(1) NOT NULL
				)');
 
				ThreadController::Create($InstallDatabase, 1, 'Hi!', 'BitBoard has been successfully installed.' . "\n\n" . 'This thread has been automatically generated. You can remove it.', true, false, 1);

				$bit_posts = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_posts (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					user_id INT(6) NOT NULL,
					thread_id INT(6) NOT NULL,
					post_content VARCHAR(4096) NOT NULL,
					post_likes INT(6) NOT NULL,
					post_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					post_edited_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
				)');

				$bit_threads_likes = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_threads_likes (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					user_id INT(6) NOT NULL,
					thread_id INT(6) NOT NULL,
					reputation_type TINYINT(1) NOT NULL
				)');

				$bit_posts_likes = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_posts_likes (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					user_id INT(6) NOT NULL,
					post_id INT(6) NOT NULL,
					reputation_type TINYINT(1) NOT NULL
				)');

				$bit_ranks = $InstallDatabase->Query('CREATE TABLE IF NOT EXISTS bit_ranks (
					id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					rank_name VARCHAR(64) NOT NULL,
					rank_format VARCHAR(256) NOT NULL,
					rank_flags INT(6) NOT NULL
				)');

				RankController::Create(
					$InstallDatabase,
					'Admin',
					'<span style="color: #B22222">{username}</span>',
					Permissions::ALL_PERMISSIONS);

				RankController::Create(
					$InstallDatabase, 
					'User', 
					'<span style="font-weight: bold">{username}</span>', 
					Permissions::VIEWING_FORUM | Permissions::CREATING_POSTS | Permissions::CREATING_THREADS
				);

				RankController::Create(
					$InstallDatabase, 
					'Banned', 
					'<span style="text-decoration: line-through">{username}</span>', 
					0
				);

				$InstallDatabase->Close();
			}

			if (isset($_POST['step_4']))
			{
				$username = isset($_POST['username']) ? $_POST['username'] : '';

				if(!UsernameUtils::Validate($username))
				{
					SessionManager::AddInformation('account', 'Username is too short! ( 4 >= )', true);
					UrlManager::Redirect('./');
					return;
				}

				$email = isset($_POST['email']) ? $_POST['email'] : '';

				if(!EmailUtils::Validate($email))
				{
					SessionManager::AddInformation('account', 'Email is not valid. Try another one.', true);
					UrlManager::Redirect('./');
					return;
				}

				$password = isset($_POST['password']) ? $_POST['password'] : '';

				if (!PasswordUtils::CheckLength($password))
				{
					SessionManager::AddInformation('account', 'Password is too short! ( 8 >= )', true);
					UrlManager::Redirect('./');
					return;
				}

				if (!PasswordUtils::CheckNumber($password))
				{
					SessionManager::AddInformation('account', 'Password needs to have atleast one number!', true);
					UrlManager::Redirect('./');
					return;
				}

				if (!PasswordUtils::CheckLetter($password))
				{
					SessionManager::AddInformation('account', 'Password needs to have atleast one character!', true);
					UrlManager::Redirect('./');
					return;
				}

				require_once './app/config.php';
				$NewUserDatabase = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

				AccountController::Create(
					$NewUserDatabase,
					$_POST['username'],
					PasswordUtils::GetHash($_POST['password']),
					$_POST['email'],
					1
				);

				$NewUserDatabase->Close();
			}

			if(isset($_POST['step_5']))
			{
				$forumName = isset($_POST['forum_name']) ? $_POST['forum_name'] : '';
				$forumDesc = isset($_POST['forum_description']) ? $_POST['forum_description'] : '';

				if(strlen($forumName <= 0))
				{
					SessionManager::AddInformation('forum', 'Forum name cannot be empty.', true);
					UrlManager::Redirect('./');
					return;
				}

				if(strlen($forumDesc <= 0) && UrlManager::Redirect('./', array('error' => 2)))
				{
					SessionManager::AddInformation('forum', 'Forum description cannot be empty.', true);
					UrlManager::Redirect('./');
					return;
				}

				require_once './app/config.php';
				$db = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

				$db->Query('UPDATE bit_settings SET forum_name = ? WHERE id = ?', $forumName, 0);
				$db->Query('UPDATE bit_settings SET forum_description = ? WHERE id = ?', $forumDesc, 0);

				$db->Close();
			}

			if (isset($_POST['step_6']))
			{
				$lock = new File('./app/install/lock');
				$lock->UpdateData('delete this file if you want to restart the installation');
				$lock->Save();

				$step = new File('./app/install/step');
				$step->Remove();

				UrlManager::Redirect();
				return;
			}

			$this->CurrentStep++;
			$this->file->UpdateData($this->CurrentStep);
			$this->file->Save();

			UrlManager::Redirect();
			return;
		}

		$this->Run();
	}

	private function Run(): void
	{
		$stepClassName = 'App\Install\Steps\Step_' . $this->CurrentStep;

		if(class_exists($stepClassName))
			$this->Step = new $stepClassName();
	}
}

?>