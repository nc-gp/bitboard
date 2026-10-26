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
     * @param array  metadata        Optional canonical, description, and social metadata.
     */
    public function __construct(string $forumName, string $forumDesc, string $stylesTemplate = '', array $metadata = [])
    {
        $canonical = (string) ($metadata['canonical'] ?? '');
        $description = (string) ($metadata['description'] ?? '');
        $siteName = (string) ($metadata['site_name'] ?? $forumName);
        $ogTitle = (string) ($metadata['og_title'] ?? $forumDesc);
        $ogDescription = (string) ($metadata['og_description'] ?? $description);
        $ogType = (string) ($metadata['og_type'] ?? 'website');
        $ogUrl = (string) ($metadata['og_url'] ?? '');
        $ogImage = (string) ($metadata['og_image'] ?? '');
        $feedUrl = (string) ($metadata['feed_url'] ?? '');

        $meta = [];

        if ($description !== '')
            $meta[] = '<meta name="description" content="' . Template::Escape($description) . '">';

        if ($canonical !== '')
            $meta[] = '<link rel="canonical" href="' . Template::Escape($canonical) . '">';

        if ($siteName !== '')
            $meta[] = '<meta property="og:site_name" content="' . Template::Escape($siteName) . '">';

        if ($ogTitle !== '')
            $meta[] = '<meta property="og:title" content="' . Template::Escape($ogTitle) . '">';

        if ($ogDescription !== '')
            $meta[] = '<meta property="og:description" content="' . Template::Escape($ogDescription) . '">';

        if ($ogType !== '')
            $meta[] = '<meta property="og:type" content="' . Template::Escape($ogType) . '">';

        if ($ogUrl !== '')
            $meta[] = '<meta property="og:url" content="' . Template::Escape($ogUrl) . '">';

        if ($ogImage !== '')
            $meta[] = '<meta property="og:image" content="' . Template::Escape($ogImage) . '">';

        $meta[] = '<meta name="twitter:card" content="summary">';

        if ($ogTitle !== '')
            $meta[] = '<meta name="twitter:title" content="' . Template::Escape($ogTitle) . '">';

        if ($ogDescription !== '')
            $meta[] = '<meta name="twitter:description" content="' . Template::Escape($ogDescription) . '">';

        if ($ogImage !== '')
            $meta[] = '<meta name="twitter:image" content="' . Template::Escape($ogImage) . '">';

        if ($feedUrl !== '')
            $meta[] = '<link rel="alternate" type="application/atom+xml" title="' . Template::Escape($forumName . ' feed') . '" href="' . Template::Escape($feedUrl) . '">';

        $this->template = new Template('head', 'head');
        $this->template->AddEscapedEntry('{forum_name}', $forumName);
        $this->template->AddEscapedEntry('{page_title}', $forumDesc);
        $this->template->AddEntry('{meta}', implode("\n\t", $meta));
        $this->template->AddEntry('{styles}', $stylesTemplate);
        $this->template->Replace();
        $this->template = $this->template->template;
    }
}

?>
