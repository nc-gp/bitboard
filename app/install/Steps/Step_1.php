<?php

namespace App\Install\Steps;

use App\Classes\Template;
use App\Classes\Install\StepBase;
use App\Interfaces\Install\StepInterface;

class Step_1 extends StepBase implements StepInterface
{
	public static function Execute()
	{
		self::$step = 1;
		self::$template = new Template("./app/install/templates/1.html");
		parent::RenderPage();
	}

	public static function Handler() 
	{
		parent::Handler();
	}
}

?>