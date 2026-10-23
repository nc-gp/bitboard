<?php

namespace App\Classes;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Sanitizes rich post/thread HTML with a small allowlist.
 *
 * The forum stores post bodies as HTML. This sanitizer keeps basic formatting,
 * links, images, and tables while removing scripts, event handlers, unsafe URL
 * schemes, and unsupported elements.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'code', 'del', 'div', 'em',
        'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
        'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 's', 'span', 'strong',
        'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr',
        'u', 'ul'
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan']
    ];

    /**
     * Sanitize an HTML fragment and return the safe markup.
     */
    public static function Sanitize(?string $html): string
    {
        $html = (string) $html;

        if ($html === '')
            return '';

        if (!class_exists(DOMDocument::class))
            return Template::Escape($html);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="bb-sanitize-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded)
            return Template::Escape($html);

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="bb-sanitize-root"]')->item(0);

        if (!$root instanceof DOMElement)
            return '';

        self::SanitizeNode($root);

        $output = '';

        foreach ($root->childNodes as $child)
            $output .= $document->saveHTML($child);

        return $output;
    }

    /**
     * Recursively remove disallowed nodes and attributes.
     */
    private static function SanitizeNode(DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--)
        {
            $child = $node->childNodes->item($i);

            if ($child === null)
                continue;

            if ($child instanceof DOMElement)
            {
                $tag = strtolower($child->tagName);

                if (!in_array($tag, self::ALLOWED_TAGS, true))
                {
                    $node->removeChild($child);
                    continue;
                }

                self::SanitizeAttributes($child);
                self::SanitizeNode($child);
                continue;
            }

            if (!$child instanceof \DOMText)
                $node->removeChild($child);
        }
    }

    /**
     * Keep only allowed attributes and validate URL values.
     */
    private static function SanitizeAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--)
        {
            $attribute = $element->attributes->item($i);

            if ($attribute === null)
                continue;

            $name = strtolower($attribute->name);

            if (!in_array($name, $allowed, true) || str_starts_with($name, 'on'))
            {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (($name === 'href' || $name === 'src') && !self::IsSafeUrl($attribute->value, $name === 'src'))
            {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($tag === 'a' && $name === 'target')
            {
                $target = strtolower(trim($attribute->value));

                if (!in_array($target, ['_blank', '_self'], true))
                {
                    $element->removeAttributeNode($attribute);
                    continue;
                }

                if ($target === '_blank')
                    $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /**
     * Allow relative URLs and the http, https, and mailto schemes.
     */
    private static function IsSafeUrl(string $url, bool $image = false): bool
    {
        $url = trim($url);

        if ($url === '')
            return false;

        $normalized = preg_replace('/[\x00-\x20]+/', '', $url);

        if ($normalized === null || $normalized === '')
            return false;

        if (str_starts_with($normalized, '#') || str_starts_with($normalized, '/'))
            return true;

        $scheme = parse_url($normalized, PHP_URL_SCHEME);

        if ($scheme === null)
            return true;

        $scheme = strtolower($scheme);

        if ($image)
            return in_array($scheme, ['http', 'https'], true);

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}

?>
