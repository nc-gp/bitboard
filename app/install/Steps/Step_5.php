<?php

namespace App\Install\Steps;

use App\Classes\SessionManager;
use App\Classes\Template;
use App\Classes\Install\StepBase;
use App\Interfaces\Install\StepInterface;

use App\Classes\Database;

class Step_5 extends StepBase implements StepInterface
{
	static string $error = '';

	public static function Execute()
	{
		self::$step = 5;
		self::$template = new Template('./app/install/templates/', '5', true);

		if(isset($_SESSION['bb-info-forum']))
		{
			$err = new Template('./app/install/templates/other/', 'error', true);
			$err->AddEntry('{error}', $_SESSION['bb-info-forum']['msg']);
			$err->Replace();

			self::$error = $err->template;

			SessionManager::RemoveInformation('forum');
		}

		self::$template->AddEntry("{error}", self::$error);

		parent::RenderPage();
	}

	public static function Handler() 
	{
		$forumName = isset($_POST['name']) ? $_POST['name'] : '';

		if(strlen($forumName <= 0))
		{
			SessionManager::AddInformation('forum', 'Forum name cannot be empty.', true);
			return;
		}

		if(strlen($forumName <= 2))
		{
			SessionManager::AddInformation('forum', 'Forum name is too short! ( 2 >= )', true);
			return;
		}

		$forumDesc = isset($_POST['description']) ? $_POST['description'] : '';

		if(strlen($forumDesc <= 0))
		{
			SessionManager::AddInformation('forum', 'Forum description cannot be empty.', true);
			return;
		}

		if(strlen($forumDesc <= 2))
		{
			SessionManager::AddInformation('forum', 'Forum description is too short! ( 2 >= )', true);
			return;
		}

		require_once './app/config.php';
		$db = new Database($config['host'], $config['user'], $config['pass'], $config['name']);

		$db->Query('UPDATE bit_settings SET forum_name = ?, forum_description = ? WHERE id = ?', "$forumName", "$forumDesc", 0);

		$db->Close();

		parent::Handler();
	}
}

?>