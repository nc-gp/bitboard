<?php

class Step
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
		if (isset($_GET['error']))
		{
			switch ($_GET['error']) {
				case '1':
					$this->error = "Username is too short. Try minimum with 4 characters.";
					break;

				case '2':
					$this->error = "Email is not valid. Try another one.";
					break;

				case '3':
					$this->error = "Password is too short. Try minimum with 8 characters.";
					break;

				case '4':
					$this->error = "In password we need atleast one number.";
					break;

				case '5':
					$this->error = "In password we need atleast one character.";
					break;
				
				default:
					$this->error = "Unknown error. Try reinstalling BitBoard.";
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

		$this->template = new Template("./app/install/templates/4.html");
		$this->template->AddEntry("{head}", $headTemplate->templ);
		$this->template->AddEntry("{error}", $errorTemplate->templ);
		$this->template->AddEntry("{footer}", $footerTemplate->templ);
		$this->template->Render(true);
	}
}

?>