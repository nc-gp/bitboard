<?php

require_once './app/helpers/url_manager.php';

require_once './app/template/template.php';

require_once './app/database/database.php';

require_once './app/forum/controllers/category.php';
require_once './app/forum/controllers/forum.php';
require_once './app/forum/controllers/account.php';
require_once './app/forum/controllers/rank.php';
require_once './app/forum/controllers/thread.php';

require_once './app/helpers/password.php';
require_once './app/helpers/username.php';
require_once './app/helpers/email.php';

require_once './app/forum/permissions.php';

class Instalation
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

				$configFile = new File('./app/database/config.php');
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
					(id,forum_name,forum_online,forum_online_msg,forum_theme,reputation_negative) 
					VALUES (?,?,?,?,?,?)', 
					array(0, 'Forum', 1, 'Back soon.', 'default', 1)
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
					VIEWING_FORUM | CREATING_POSTS | EDITING_POSTS | DELETING_POSTS | CREATING_THREADS | EDITING_THREADS | DELETING_THREADS | PINNING_THREADS | LOCKING_THREADS);
				RankController::Create($InstallDatabase, 'User', '<span style="font-weight: bold">{username}</span>', VIEWING_FORUM | CREATING_THREADS | CREATING_POSTS);
				RankController::Create($InstallDatabase, 'Banned', '<span style="text-decoration: line-through">{username}</span>', 0);

				$InstallDatabase->Close();
			}

			if (isset($_POST['step_4']))
			{
				$username = isset($_POST['username']) ? new Username($_POST['username']) : '';
				$email = 	isset($_POST['email']) ?    new Email(   $_POST['email']) 	 : '';
				$password = isset($_POST['password']) ? new Password($_POST['password']) : '';

				if (!$username->Validate() && UrlManager::Redirect('./', array('error' => 1)))
					return;

				if (!$password->CheckLength() && UrlManager::Redirect('./', array('error' => 3)))
					return;

				if (!$password->CheckNumber() && UrlManager::Redirect('./', array('error' => 4)))
					return;

				if (!$password->CheckLetter() && UrlManager::Redirect('./', array('error' => 5)))
					return;

				require_once './app/database/config.php';
				$NewUserDatabase = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

				AccountController::Create($NewUserDatabase, $username->String, $password->GetHash(), $email->String, 1);

				$NewUserDatabase->Close();
			}

			if(isset($_POST['step_5']))
			{
				$forumName = isset($_POST['forum_name']) ? $_POST['forum_name'] : '';
				$forumDesc = isset($_POST['forum_description']) ? $_POST['forum_description'] : '';

				if(strlen($forumName <= 0) && UrlManager::Redirect('./', array('error' => 1)))
					return;

				if(strlen($forumDesc <= 0) && UrlManager::Redirect('./', array('error' => 2)))
					return;

				require_once './app/database/config.php';
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
		require_once './app/install/steps/' . $this->CurrentStep . '.php';
		$this->Step = new Step();
	}
}

?>