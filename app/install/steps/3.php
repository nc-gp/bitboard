<?php

class Step
{
	protected int $ActualStep = 3;
	protected $template;

	public function __construct()
	{
		$this->Do();
	}


	// TODO: error handling for mysql data. is valid connection?
	protected function Do()
	{
		$headTemplate = new Template("./app/install/templates/head.html");
		$headTemplate->AddEntry("{step}", $this->ActualStep);
		$headTemplate->Replace();

		$footerTemplate = new Template("./app/install/templates/footer.html");
		$footerTemplate->AddEntry("{year}", date("Y"));
		$footerTemplate->Replace();

		$this->template = new Template("./app/install/templates/3.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->Render(true);
	}
}

?>