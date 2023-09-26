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
		self::$template = new Template("./app/install/templates/2.html");

		$phpTemplate = new Template("./app/install/templates/other/2_php_ok.html");
		$mysqlTemplate = new Template("./app/install/templates/other/2_mysql_ok.html");

		if(!version_compare(phpversion(), "8.0.0", ">="))
			$phpTemplate = new Template("./app/install/templates/other/2_php_bad.html");

		if(!extension_loaded("mysqli"))
			$mysqlTemplate = new Template("./app/install/templates/other/2_mysql_bad.html");

		$phpTemplate->AddEntry("{phpversion}", phpversion());
		$phpTemplate->Replace();

		self::$template->AddEntry("{php}", $phpTemplate->templ);
		self::$template->AddEntry("{mysql}", $mysqlTemplate->templ);

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
		if(!extension_loaded("mysqli"))
		{
			SessionManager::AddInformation('mysql', 'Mysql is not installed on the server!', true);
			return;
		}

		parent::Handler();
	}
}

?>