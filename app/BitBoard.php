<?php

namespace App;

use App\Classes\Database;
use App\Classes\PageFactory;
use App\Classes\File;
Use App\Classes\UrlManager;
use App\Classes\AdminPath;
use App\Classes\Migrator;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\Template;
use App\Forum\Controllers\AccountController;
use App\Forum\Controllers\BanController;
use App\Forum\Controllers\ThemeController;
use App\Forum\Controllers\ViewingController;
use App\Forum\Pages\_500Page;

use App\Forum\Structs\ForumDataStruct;

use Throwable;

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
		Migrator::Run($this->database);
		$this->data = new ForumDataStruct($this->database->Query("SELECT * FROM bit_settings WHERE settings_key = 'core'")->FetchArray());

		define('BB_THEME', ThemeController::ResolveTheme($this->database, (string) $this->data->forum_theme));

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
			$canBypassOffline = SessionManager::IsStaff()
				|| SessionManager::HasPermission(Permissions::VIEWING_FORUM_LOCKED);

			if((count($SplitedURL) > 1 || empty($SplitedURL)) && !$canBypassOffline && $firstParameter !== 'login')
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
			
			// Bans are stored as records so reasons and expiry survive rank changes.
			// Legacy Banned-rank accounts are still caught by the permission check.
			$activeBan = BanController::GetActiveForUser($this->database, (int) $_SESSION['bitboard_user']->id);
			$isBanned = !empty($activeBan) || !SessionManager::HasPermission(Permissions::VIEWING_FORUM);

			if($isBanned && $firstParameter !== 'banned' && $firstParameter !== 'logout')
				UrlManager::Redirect(UrlManager::GetPath() . 'banned');

			// Remember where the member is browsing for the "who is viewing" lists.
			$viewingType = '';
			$viewingID = 0;

			if (isset($SplitedURL[1]) && is_numeric($SplitedURL[1])
				&& in_array($SplitedURL[0] ?? '', ['forum', 'subforum', 'thread'], true))
			{
				$viewingType = (string) $SplitedURL[0];
				$viewingID = (int) $SplitedURL[1];
			}

			ViewingController::Track($this->database, (int) $_SESSION['bitboard_user']->id, $viewingType, $viewingID);
		}

		if($this->data->forum_force_login && !SessionManager::IsLogged())
		{
			if($firstParameter !== 'login')
				UrlManager::Redirect(UrlManager::GetPath() . 'login');
		}

		if (!empty($SplitedURL))
		{
			$this->data->actionParameters = $SplitedURL;

			$pageName = $firstParameter;
			$adminPath = AdminPath::FromSettings($this->data);

			if ($firstParameter === $adminPath)
				$pageName = 'admin';
			elseif (strtolower($firstParameter) === 'admin')
				$pageName = '_404';

			$this->RenderPage($pageName);

			return; 
		}

		$this->RenderPage('index');
	}

	private function RenderPage(string $pageName): void
	{
		try
		{
			PageFactory::CreatePage($pageName, $this->database, $this->data);
		}
		catch (Throwable $e)
		{
			error_log('BitBoard page error: ' . $e->getMessage());

			try
			{
				Template::SetTheme('default');
				(new _500Page($this->database, $this->data))->Do();
			}
			catch (Throwable $fallback)
			{
				error_log('BitBoard error page failed: ' . $fallback->getMessage());
				http_response_code(500);
				echo 'Internal Server Error';
			}
		}
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