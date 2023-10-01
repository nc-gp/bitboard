<?php

namespace App\Install;

use App\Classes\UrlManager;
use App\Classes\File;

use App\Classes\Console;

class Setup
{
	private File $step;
	private int $currentStep;

	public function __construct()
	{
		Console::Log($_SESSION);
		$this->UrlHandler();

		$this->step = new File('./app/install/step');
		$this->currentStep = $this->step->data == -1 ? 1 : $this->step->data;

		if(isset($_POST['step']))
		{
			$this->ExecuteStepHandler();
			UrlManager::Redirect();
			return;
		}

		if(isset($_SESSION['next'])) // hmm..
		{
			unset($_SESSION['next']);
			session_write_close();
			$this->UpdateStep();
			return;
		}

		$this->ExecuteStep();
	}

	private function UrlHandler()
	{
		if(isset($_GET['action']))
			UrlManager::Redirect(UrlManager::GetPath());
	}

	private function UpdateStep()
	{
		$this->currentStep++;
		$this->step->UpdateData($this->currentStep);
		$this->step->Save();

		UrlManager::Redirect(UrlManager::GetPath());
	}

	private function ExecuteStep()
	{
		$className = 'App\Install\Steps\Step_' . $this->currentStep;

		if(class_exists($className))
			$className::Execute();
	}

	private function ExecuteStepHandler()
	{
		$className = 'App\Install\Steps\Step_' . $_POST['step'];

		if(class_exists($className))
			$className::Handler();
	}
}

?>