<?php

require_once './app/template/template.php';
require_once './app/helpers/url_manager.php';

/**
 * The StylesWidget class provides methods for rendering styles using a template.
 */
class StylesWidget
{
    public $Template;

    /**
     * Constructor to initialize a StylesWidget with a theme and template path.
     *
     * @param string $theme        The theme to use.
     * @param string $templatePath The path to the template file.
     */
    public function __construct(string $theme, string $templatePath)
    {
        $this->Template = new Template('./themes/' . $theme . $templatePath);
        $this->Do();
    }

    /**
     * Perform the rendering of styles using the template.
     */
    private function Do()
    {
        $this->Template->AddEntry('{server_url}', UrlManager::GetPath());
        $this->Template->Replace();
    }
}

?>