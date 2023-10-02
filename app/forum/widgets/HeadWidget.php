<?php

namespace App\Forum\Widgets;

use App\Classes\Template;

/**
 * HeadWidget represents a widget for rendering the <head> section of an HTML page.
 */
class HeadWidget
{
    /**
     * @var string The HTML template for the <head> section.
     */
    public $template;

    /**
     * Constructs a new HeadWidget instance.
     *
     * @param string forumName       The name of the forum.
     * @param string forumDesc       The description of the forum, used as the page title.
     * @param string stylesTemplate  Additional styles to be included in the <head> section.
     */
    public function __construct(string $forumName, string $forumDesc, string $stylesTemplate = '')
    {
        $this->template = new Template('head', 'head');
        $this->template->AddEntry('{forum_name}', $forumName);
        $this->template->AddEntry('{page_title}', $forumDesc);
        $this->template->AddEntry('{styles}', $stylesTemplate);
        $this->template->Replace();
        $this->template = $this->template->template;
    }
}

?>