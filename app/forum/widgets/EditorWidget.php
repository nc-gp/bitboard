<?php

namespace App\Forum\Widgets;

use App\Classes\Template;

/**
 * Formatting toolbar for the Markdown editor.
 */
class EditorWidget
{
    public $Template = '';

    public function __construct()
    {
        $template = new Template('editor', 'toolbar');
        $template->Replace();
        $this->Template = $template->template;
    }
}
