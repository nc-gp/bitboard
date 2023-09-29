<?php

namespace App\Install\Steps;

use App\Classes\Template;
use App\Classes\Install\StepBase;
use App\Interfaces\Install\StepInterface;
use App\Classes\SessionManager;

class Step_2 extends StepBase implements StepInterface
{
	static string $error = '';

	public static function Execute()
	{
		self::$step = 2;
		self::$template = new Template('./app/install/templates/', '2', true);

		$phpTemplate = new Template('./app/install/templates/other/', '2_php_ok', true);
		$mysqlTemplate = new Template('./app/install/templates/other/', '2_mysql_ok', true);

		if(!version_compare(phpversion(), '8.0.0', '>='))
			$phpTemplate = new Template("./app/install/templates/other/", '2_php_bad', true);

		if(!extension_loaded("mysqli"))
			$mysqlTemplate = new Template('./app/install/templates/other/', '2_mysql_bad', true);

		$phpTemplate->AddEntry("{phpversion}", phpversion());
		$phpTemplate->Replace();

		self::$template->AddEntry("{php}", $phpTemplate->template);
		self::$template->AddEntry("{mysql}", $mysqlTemplate->template);

		if(isset($_SESSION['bb-info-mysql']))
		{
			$err = new Template('./app/install/templates/other/', 'error', true);
			$err->AddEntry('{error}', $_SESSION['bb-info-mysql']['msg']);
			$err->Replace();

			self::$error = $err->template;

			SessionManager::RemoveInformation('mysql');
		}

		self::$template->AddEntry("{error}", self::$error);

		parent::RenderPage();
	}

	public static function Handler()
	{
		if(!extension_loaded("mysqli"))
		{
			SessionManager::AddInformation('mysql', 'Mysql is not installed on the server!', true);
			return;
		}

		parent::Handler();
	}
}

?>