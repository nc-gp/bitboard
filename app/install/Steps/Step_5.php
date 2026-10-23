<?php

namespace App\Install\Steps;

use Exception;

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
			$err->AddJsEntry('{error}', $_SESSION['bb-info-forum']['msg']);
			$err->Replace();

			self::$error = $err->template;

			SessionManager::RemoveInformation('forum');
		}

		self::$template->AddEntry("{error}", self::$error);

		parent::RenderPage();
	}

	public static function Handler() 
	{
		$forumName = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';

		if($forumName === '')
		{
			SessionManager::AddInformation('forum', 'Forum name cannot be empty.', true);
			return;
		}

		if(mb_strlen($forumName) < 2)
		{
			SessionManager::AddInformation('forum', 'Forum name must be at least 2 characters long.', true);
			return;
		}

		if(mb_strlen($forumName) > 64)
		{
			SessionManager::AddInformation('forum', 'Forum name cannot be longer than 64 characters.', true);
			return;
		}

		$forumDesc = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';

		if($forumDesc === '')
		{
			SessionManager::AddInformation('forum', 'Forum description cannot be empty.', true);
			return;
		}

		if(mb_strlen($forumDesc) < 2)
		{
			SessionManager::AddInformation('forum', 'Forum description must be at least 2 characters long.', true);
			return;
		}

		if(mb_strlen($forumDesc) > 256)
		{
			SessionManager::AddInformation('forum', 'Forum description cannot be longer than 256 characters.', true);
			return;
		}

		require_once './app/config.php';

		try {
			$db = new Database($config['host'], $config['user'], $config['pass']);
			$db->SelectDatabase($config['name']);

			$db->Query('UPDATE bit_settings SET forum_name = ?, forum_description = ? WHERE id = ?', $forumName, $forumDesc, 0);

			$db->Close();
		}
		catch (Exception $e) {
			SessionManager::AddInformation('forum', $e->getMessage(), true);
			return;
		}

		parent::Handler();
	}
}

?>