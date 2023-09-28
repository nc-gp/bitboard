<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\UrlManager;

/**
 * The FooterWidget class provides methods for rendering a footer section using a template.
 */
class FooterWidget
{
    public $Template;

    /**
     * Constructor to initialize a FooterWidget with a theme.
     *
     * @param string $theme The theme to use for rendering the footer.
     */
    public function __construct()
    {
        $this->Template = new Template('footer', 'footer');
        $this->Do();
    }

    /**
     * Perform the rendering of the footer section using the template.
     */
    private function Do()
    {
        $this->Template->AddEntry('{server_url}', UrlManager::GetPath());
        $this->Template->Replace();
        
        // TODO: Add this to forum settings if someone wants to keep the credits to BitBoard, but they don't need to.
        $this->Template->template .= '<div id="bb-foot">Powered by BitBoard</div>';
    }
}


?>