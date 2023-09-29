<?php

namespace App;

session_start();

use App\Classes\Database;
use App\Classes\PageFactory;
use App\Classes\File;
Use App\Classes\UrlManager;
use App\Classes\Console;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Controllers\AccountController;
use App\Forum\Pages\IndexPage;

use App\Install\Setup;

use Exception;

class BB
{
	static public array $Data;
}

class BitBoard
{
	private $file;
	private $Install;
	private $database;

	public function __construct()
	{
		$this->file = new File('./app/install/lock');
	}

	public function Run(): void
	{
		if (!$this->IsInstalled())
		{
			$this->Install = new Setup();
			return;
		}

		require_once './app/config.php';

		$this->database = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

		BB::$Data = $this->database->Query('SELECT * FROM bit_settings')->FetchArray();
		
		$this->Do();

		$this->database->Close();
	}

	private function Do()
	{
		Console::Log($_SESSION);

		$actionParameters = isset($_GET['action']) ? $_GET['action'] : '';
		$SplitedURL = !empty($actionParameters) ? explode('/', $_GET['action']) : array();

		if(!$this->IsOnline())
		{
			if(count($SplitedURL) > 1 || count($SplitedURL) <= 0)
			{
				if(!SessionManager::IsLogged() || SessionManager::IsLogged() && !$_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM_LOCKED))
				{
					UrlManager::Redirect(UrlManager::GetPath() . 'offline');
					return;
				}
			}
		}

		if(SessionManager::IsLogged())
		{
			AccountController::UpdateLastActive($this->database, $_SESSION['bitboard_user']->id);
			$_SESSION['bitboard_user']->Update($this->database);
			
			// Checking is user banned. (Reality this is checking if user has permission to view the forum)
			if(!$_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM))
			{
				if(count($SplitedURL) > 1 || count($SplitedURL) <= 0)
				{
					UrlManager::Redirect(UrlManager::GetPath() . 'banned');
					return;
				}
			}
		}

		// todo: option force to login/register

		if (count($SplitedURL) > 0)
		{
			$SplitedURL = explode('/', $_GET['action']);

			BB::$Data['actionParameters'] = $SplitedURL;

			try {
				$instance = PageFactory::CreatePage($SplitedURL[0], $this->database, BB::$Data);
			} catch (Exception $e) {
				Console::Error($e->getMessage());
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