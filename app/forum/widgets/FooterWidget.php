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
     * @param string $theme The theme to use for rendering the footer.
     */
    public function __construct()
    {
        $this->template = new Template('footer', 'footer');
        // TODO: Add this to forum settings if someone wants to keep the credits to BitBoard, but they don't need to.
        $this->template->template .= '<div id="bb-foot">Powered by BitBoard</div>';
        $this->template = $this->template->template;
    }
}


?>