<?php

session_start();

require_once './app/database/database.php';
require_once './app/install/install.php';
require_once './app/file/file.php';
require_once './app/template/template.php';

require_once './app/forum/pages/offline.php';
require_once './app/forum/pages/index.php';
require_once './app/forum/pages/members.php';
require_once './app/forum/pages/forum.php';
require_once './app/forum/pages/thread.php';

require_once './app/helpers/log.php';

class BB
{
	static public array $Data;
}

class BitBoard
{
	static public array $Data;
	private $file;
	private $Install;
	private $database;
	private $account;

	public function __construct()
	{
		$this->file = new File('./app/install/lock');
	}

	public function Run(): void
	{
		if (!$this->IsInstalled())
		{
			$this->Install = new Instalation();
			return;
		}

		require_once './app/database/config.php';

		$this->database = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

		BB::$Data = $this->database->Query('SELECT * FROM bit_settings')->FetchArray();
		
		$this->Do();

		$this->database->Close();
	}

	private function Do()
	{
		if(!$this->IsOnline())
		{
			new OfflinePage(BB::$Data);
			return;
		}

		// todo: option force to login/register

		if (isset($_GET['action']))
		{
			$SplitedURL = explode('/', $_GET['action']);

			$page = $SplitedURL[0];

			Log::Log('URL: ' . $_GET['action']);

			switch($page)
			{
				case 'members':
					new MembersPage($this->database, BB::$Data);
					break;

				case 'forum':
					new ForumPage($this->database, BB::$Data);
					break;

				case 'thread':
					new ThreadPage($this->database, BB::$Data);
					break;
			}

			return; 
		}

		new IndexPage($this->database, BB::$Data);
	}

	private function IsInstalled(): bool
	{
		return $this->file->Exists();
	}

	private function IsOnline(): bool
	{
		return BB::$Data['forum_online'];
	}
}

?>