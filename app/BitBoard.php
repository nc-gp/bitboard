<?php

namespace App;

session_start();

use App\Classes\Database;
use App\Classes\PageFactory;
use App\Classes\File;
Use App\Classes\UrlManager;
use App\Classes\Console;
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
	private $account;

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
		if(SessionManager::IsLogged())
			AccountController::UpdateLastActive($this->database, $_SESSION['bitboard_user']['id']);

		$actionParameters = isset($_GET['action']) ? $_GET['action'] : '';

		if(!$this->IsOnline())
		{
			if(empty($actionParameters))
			{
				UrlManager::Redirect('./offline');
				return;
			}
		}

		// todo: option force to login/register

		if (!empty($actionParameters))
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