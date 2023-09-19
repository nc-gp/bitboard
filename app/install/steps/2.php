<?php

class Step
{
	protected int $ActualStep = 2;
	protected $template;

	public function __construct()
	{
		$this->Do();
	}

	protected function Do()
	{
		$phpTemplate = new Template("./app/install/templates/other/2_php_ok.html");

		if(!version_compare(phpversion(), "8.0.0", ">="))
			$phpTemplate = new Template("./app/install/templates/other/2_php_bad.html");

		$phpTemplate->AddEntry("{phpversion}", phpversion());
		$phpTemplate->Replace();

		$headTemplate = new Template("./app/install/templates/head.html");
		$headTemplate->AddEntry("{step}", $this->ActualStep);
		$headTemplate->Replace();

		$footerTemplate = new Template("./app/install/templates/footer.html");
		$footerTemplate->AddEntry("{year}", date("Y"));
		$footerTemplate->Replace();

		$this->template = new Template("./app/install/templates/2.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->AddEntry("{php}", $phpTemplate->templ);
		$this->template->Render(true);
	}
}

?>