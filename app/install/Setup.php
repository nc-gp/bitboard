<?php

namespace App\Install;

use App\Classes\UrlManager;
use App\Classes\File;
use App\Classes\SessionManager;

class Setup
{
	private const TOTAL_STEPS = 6;

	private File $step;
	private int $currentStep;

	public function __construct()
	{
		// The installer must never run after setup, even if it is instantiated
		// directly instead of going through BitBoard::Run().
		if ((new File('./app/install/lock'))->Exists())
		{
			http_response_code(404);
			exit;
		}

		SessionManager::Start();
		$this->UrlHandler();

		$this->step = new File('./app/install/step');
		$currentStep = $this->step->data === -1 ? 1 : (int) $this->step->data;
		$this->currentStep = ($currentStep >= 1 && $currentStep <= self::TOTAL_STEPS) ? $currentStep : 1;

		if(isset($_POST['step']))
		{
			$this->ExecuteStepHandler();
			UrlManager::Redirect(UrlManager::GetPath());
		}

		if(isset($_SESSION['next'])) // hmm..
		{
			unset($_SESSION['next']);
			session_write_close();
			$this->UpdateStep();
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
		$postedStep = filter_var($_POST['step'] ?? null, FILTER_VALIDATE_INT);

		if ($postedStep === false || $postedStep < 1 || $postedStep > self::TOTAL_STEPS || $postedStep !== $this->currentStep)
		{
			http_response_code(400);
			exit;
		}

		if (!SessionManager::ValidateCsrfToken($_POST['csrf_token'] ?? null))
		{
			http_response_code(403);
			exit;
		}

		$className = 'App\Install\Steps\Step_' . $postedStep;

		if(class_exists($className))
			$className::Handler();
	}
}

?>