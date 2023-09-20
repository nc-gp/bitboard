<?php

class Step
{
	protected int $ActualStep = 3;
	protected $template;
	protected string $error = '';

	public function __construct()
	{
		$this->Do();
	}

	protected function CheckError()
	{
		if(isset($_GET['error']))
		{
			switch ($_GET['error'])
			{
				case '1':
					$this->error = isset($_GET['msg']) ? $_GET['msg'] : 'Unknown error';
					break;
			}
		}
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

		$this->template = new Template("./app/install/templates/3.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{error}", $errorTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->Render(true);
	}
}

?>