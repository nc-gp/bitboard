<?php

namespace App\Install\Steps;

use App\Classes\Install\StepBase;
use App\Classes\Template;
use App\Interfaces\Install\StepInterface;
use App\Classes\File;

class Step_6 extends StepBase implements StepInterface
{
	public static function Execute()
	{
		self::$step = 6;
		self::$template = new Template("./app/install/templates/6.html");

		parent::RenderPage();
	}

	public static function Handler()
	{
		$lock = new File('./app/install/lock');
		$lock->UpdateData('delete this file if you want to restart the installation');
		$lock->Save();

		$step = new File('./app/install/step');
		$step->Remove();

		parent::Handler();
	}
}

?>