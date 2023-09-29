<?php

namespace App\Classes\Install;

use App\Classes\Template;

class StepBase
{
    protected static int $step;
    protected static Template $template;

    protected static function RenderPage(): void
    {
        self::CommonSetup();
        self::$template->Render(true);
    }

    private static function CommonSetup(): void
    {
        $headTemplate = new Template('./app/install/templates/', 'head', true);
		$headTemplate->AddEntry("{step}", self::$step);
		$headTemplate->Replace();

		$footerTemplate = new Template('./app/install/templates/', 'footer', true);
		$footerTemplate->AddEntry('{year"', date('Y'));
		$footerTemplate->Replace();

        self::$template->AddEntry('{head}', $headTemplate->template);
        self::$template->AddEntry('{footer}', $footerTemplate->template);
    }

    protected static function Handler()
    {
        $_SESSION['next'] = true;
    }
}

?>