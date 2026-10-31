<?php

namespace App\Classes\Install;

use App\Classes\Template;
use App\Classes\SessionManager;

class StepBase
{
    private const STEPS = [
        1 => 'Welcome',
        2 => 'Requirements',
        3 => 'Database',
        4 => 'Administrator',
        5 => 'Forum',
        6 => 'Finish',
    ];

    protected static int $step;
    protected static Template $template;

    protected static function RenderPage(): void
    {
        $headTemplate = new Template('./app/install/templates/', 'head', true);
        $headTemplate->AddEntry('{step}', self::$step);
        $headTemplate->Replace();

        $footerTemplate = new Template('./app/install/templates/', 'footer', true);
        $footerTemplate->AddEntry('{year}', date('Y'));
        $footerTemplate->Replace();

        self::$template->AddEscapedEntry('{csrf}', SessionManager::CsrfToken());
        self::$template->AddEscapedEntry('{step}', self::$step);
        self::$template->AddEscapedEntry('{total_steps}', count(self::STEPS));
        self::$template->Replace();

        $layout = new Template('./app/install/templates/', 'layout', true);
        $layout->AddEntry('{head}', $headTemplate->template);
        $layout->AddEntry('{footer}', $footerTemplate->template);
        $layout->AddEntry('{steps}', self::BuildSteps());
        $layout->AddEscapedEntry('{step}', self::$step);
        $layout->AddEscapedEntry('{total_steps}', count(self::STEPS));
        $layout->AddEntry('{content}', self::$template->template);
        $layout->Replace();
        $layout->Render();
    }

    private static function BuildSteps(): string
    {
        $items = '';

        foreach (self::STEPS as $step => $label)
        {
            $state = $step < self::$step ? 'is-complete' : ($step === self::$step ? 'is-current' : '');

            $item = new Template('./app/install/templates/', 'step_item', true);
            $item->AddEscapedEntry('{number}', str_pad((string) $step, 2, '0', STR_PAD_LEFT));
            $item->AddEscapedEntry('{label}', $label);
            $item->AddEntry('{class}', $state);
            $item->AddEntry('{current}', $step === self::$step ? ' aria-current="step"' : '');
            $item->Replace();

            $items .= $item->template;
        }

        $steps = new Template('./app/install/templates/', 'steps', true);
        $steps->AddEntry('{items}', $items);
        $steps->Replace();

        return $steps->template;
    }

    protected static function Handler()
    {
        $_SESSION['next'] = true;
    }
}
