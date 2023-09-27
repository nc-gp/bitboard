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

use Exception;

class Step_3 extends StepBase implements StepInterface
{
	private static string $error = '';

	public static function Execute()
	{
		self::$step = 3;
		self::$template = new Template("./app/install/templates/3.html");

		if(isset($_SESSION['bb-info-mysql']))
		{
			$err = new Template('./app/install/templates/other/error.html');
			$err->AddEntry('{error}', $_SESSION['bb-info-mysql']['msg']);
			$err->Replace();

			self::$error = $err->templ;

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

		ThreadController::Create(
			$InstallDatabase, 
			1, 
			'Hi!', 
			'<h2>Welcome to Bitboard!</h2><br>
			<p>Bitboard has been successfully installed.</p><br>
			<p>If you have any questions or need assistance, feel free to explore our issues section on github. We\'re here to make your experience with BitBoard as smooth as possible.</p><br><br>
			<p>Thank you for choosing BitBoard. We hope you enjoy using our app to its fullest!</p>', 
			true,
			false, 
			1
		);

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
			Permissions::$All);

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

		parent::Handler();
	}
}

?>