<?php

namespace App\Install\Steps;

use App\Classes\SessionManager;
use App\Classes\Template;

class Step_4
{
	protected int $ActualStep = 4;
	protected $template;
	
	protected string $error = '';

	public function __construct()
	{
		$this->Do();
	}

	protected function CheckError()
	{
		if(!isset($_SESSION['bb-info-account']))
			return;

		$this->error = $_SESSION['bb-info-account']['msg'];

		SessionManager::RemoveInformation('account');
	}

	protected function Do()
	{
		$this->CheckError();

		$headTemplate = new Template("./app/install/templates/head.html");
		$headTemplate->AddEntry("{step}", $this->ActualStep);
		$headTemplate->Replace();

		$footerTemplate = new Template("./app/install/templates/footer.html");
		$footerTemplate->AddEntry("{year}", date("Y"));
		$footerTemplate->Replace();

		$errorTemplate = new Template("./app/install/templates/other/error.html");
		$errorTemplate->AddEntry("{error}", $this->error);
		$errorTemplate->Replace();

		$this->template = new Template("./app/install/templates/4.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{error}", $errorTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->Render(true);
	}
}

?>