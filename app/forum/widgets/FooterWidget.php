<?php

namespace App\Forum\Widgets;

use App\Classes\Template;

/**
 * The FooterWidget class provides methods for rendering a footer section using a template.
 */
class FooterWidget
{
    public $template;

    /**
     * Constructor to initialize a FooterWidget with a theme.
     *
     * @param bool $showCredits Whether to show the BitBoard credit line.
     */
    public function __construct(bool $showCredits = true)
    {
        $this->template = new Template('footer', 'footer');

        if ($showCredits)
            $this->template->template .= '<div id="bb-foot">Powered by BitBoard</div>';

        $this->template = $this->template->template;
    }
}


?>