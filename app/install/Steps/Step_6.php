<?php

namespace App\Install\Steps;

use App\Classes\Template;

class Step_6
{
	protected int $ActualStep = 5;
	protected $template;

	public function __construct()
	{
		$this->Do();
	}

	protected function Do()
	{
		$headTemplate = new Template("./app/install/templates/head.html");
		$headTemplate->AddEntry("{step}", $this->ActualStep);
		$headTemplate->Replace();

		$footerTemplate = new Template("./app/install/templates/footer.html");
		$footerTemplate->AddEntry("{year}", date("Y"));
		$footerTemplate->Replace();

		$this->template = new Template("./app/install/templates/6.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->Render(true);
	}
}

?>