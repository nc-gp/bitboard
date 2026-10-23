<?php

namespace App;

use App\Classes\Database;
use App\Classes\PageFactory;
use App\Classes\File;
Use App\Classes\UrlManager;
use App\Classes\Console;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Controllers\AccountController;

use App\Forum\Structs\ForumDataStruct;

use Exception;

SessionManager::Start();

class BitBoard
{
	private File $lock;
	private ForumDataStruct $data;
	private Database $database;

	public function __construct()
	{
		$this->lock = new File('./app/install/lock');
	}

	public function Run(): void
	{
		if (!$this->IsInstalled())
		{
			new \App\Install\Setup();
			return;
		}

		require_once './app/config.php';

		$this->database = new Database($config['host'], $config['user'], $config['pass']);
		$this->database->SelectDatabase($config['name']);
		$this->data = new ForumDataStruct($this->database->Query('SELECT * FROM bit_settings')->FetchArray());

		define('BB_THEME', $this->data->forum_theme);

		$this->Do();
		$this->database->Close();
	}

	private function Do()
	{
		//SessionManager::Delete();
		$actionParameters = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
		$SplitedURL = $actionParameters !== '' ? explode('/', $actionParameters) : array();
		$firstParameter = $SplitedURL[0] ?? '';

		if(!$this->IsOnline())
		{
			$canViewLocked = SessionManager::IsLogged()
				&& isset($_SESSION['bitboard_user'])
				&& $_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM_LOCKED);

			if((count($SplitedURL) > 1 || empty($SplitedURL)) && !$canViewLocked && $firstParameter !== 'login')
				UrlManager::Redirect(UrlManager::GetPath() . 'offline');
		}

		if(SessionManager::IsLogged())
		{
			// Last-active only needs minute-level accuracy. Avoid a write on every request.
			$now = time();
			if(!isset($_SESSION['bb_last_active_update']) || ($now - $_SESSION['bb_last_active_update']) >= 60)
			{
				AccountController::UpdateLastActive($this->database, $_SESSION['bitboard_user']->id);
				$_SESSION['bb_last_active_update'] = $now;
			}

			$_SESSION['bitboard_user']->Update($this->database);
			
			// Checking is user banned. (Reality this is checking if user has permission to view the forum)
			if(!$_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM))
			{
				if(count($SplitedURL) > 1 || empty($SplitedURL))
					UrlManager::Redirect(UrlManager::GetPath() . 'banned');
			}
		}

		if($this->data->forum_force_login && !SessionManager::IsLogged())
		{
			if($firstParameter !== 'login')
				UrlManager::Redirect(UrlManager::GetPath() . 'login');
		}

		if (!empty($SplitedURL))
		{
			$this->data->actionParameters = $SplitedURL;

			try {
				$instance = PageFactory::CreatePage($firstParameter, $this->database, $this->data);
			} catch (Exception $e) {
				Console::Error($e->getMessage());
			}

			return; 
		}

		PageFactory::CreatePage('index', $this->database, $this->data);
	}

	private function IsInstalled(): bool
	{
		return $this->lock->Exists();
	}

	private function IsOnline(): bool
	{
		return $this->data->forum_online;
	}
}

?>