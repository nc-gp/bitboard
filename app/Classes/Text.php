<?php

namespace App\Classes;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Small text helpers used by search results, feeds, metadata, and posting forms.
 */
class Text
{
    /**
     * Turn rich content into a plain-text excerpt.
     *
     * @param string $html   The source HTML.
     * @param int    $length The maximum excerpt length.
     *
     * @return string The plain-text excerpt.
     */
    public static function Excerpt(string $html, int $length = 160): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $html) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($length > 3 && mb_strlen($text) > $length)
            $text = mb_substr($text, 0, $length - 3) . '...';

        return $text;
    }

    /**
     * Convert stored HTML back into editable Markdown.
     */
    public static function FromHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '')
            return '';

        if (!class_exists(DOMDocument::class))
            return self::FromHtmlFallback($html);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="bb-editor-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded)
            return self::FromHtmlFallback($html);

        $root = (new DOMXPath($document))->query('//*[@id="bb-editor-root"]')->item(0);

        if (!$root instanceof DOMElement)
            return self::FromHtmlFallback($html);

        $text = self::NodeToMarkdown($root);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Convert Markdown from a posting form into safe HTML.
     *
     * Raw HTML is escaped by the renderer, and the stored result is sanitized
     * again before it reaches the database.
     */
    public static function ToHtml(string $text): string
    {
        return Markdown::ToHtml($text);
    }

    /**
     * Build a plain-text quote block for the reply form.
     *
     * Nested quote markers are flattened so quoting a post that already
     * contains quotes does not create deeper nesting.
     */
    public static function Quote(string $html, string $author): string
    {
        $text = self::FromHtml($html);
        $lines = preg_split('/\n/', $text) ?: [];
        $quoted = [];

        foreach ($lines as $line)
        {
            $line = preg_replace('/^(?:\s*>\s?)+/', '', $line) ?? '';
            $quoted[] = trim($line) === '' ? '>' : '> ' . rtrim($line);
        }

        while (count($quoted) > 0 && $quoted[0] === '>')
            array_shift($quoted);

        while (count($quoted) > 0 && $quoted[count($quoted) - 1] === '>')
            array_pop($quoted);

        $header = '> ' . trim($author) . ' wrote:';

        return count($quoted) > 0
            ? $header . "\n" . implode("\n", $quoted)
            : $header;
    }

    /**
     * Recursively convert an HTML node tree into Markdown.
     */
    private static function NodeToMarkdown(DOMNode $node): string
    {
        $output = '';

        foreach ($node->childNodes as $child)
        {
            if ($child instanceof \DOMText)
            {
                $output .= $child->nodeValue;
                continue;
            }

            if (!$child instanceof DOMElement)
                continue;

            $tag = strtolower($child->tagName);

            switch ($tag)
            {
                case 'br':
                    $output .= "\n";
                    break;

                case 'p':
                case 'div':
                    $output .= self::NodeToMarkdown($child) . "\n\n";
                    break;

                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $level = (int) substr($tag, 1);
                    $output .= str_repeat('#', $level) . ' ' . trim(self::NodeToMarkdown($child)) . "\n\n";
                    break;

                case 'strong':
                case 'b':
                    $output .= '**' . self::NodeToMarkdown($child) . '**';
                    break;

                case 'em':
                case 'i':
                    $output .= '*' . self::NodeToMarkdown($child) . '*';
                    break;

                case 'del':
                case 's':
                case 'strike':
                    $output .= '~~' . self::NodeToMarkdown($child) . '~~';
                    break;

                case 'code':
                    $parentTag = $child->parentNode instanceof DOMElement
                        ? strtolower($child->parentNode->tagName)
                        : '';

                    $output .= $parentTag === 'pre'
                        ? $child->textContent
                        : '`' . $child->textContent . '`';
                    break;

                case 'pre':
                    $output .= "```\n" . rtrim($child->textContent, "\n") . "\n```\n\n";
                    break;

                case 'blockquote':
                    $inner = trim(self::NodeToMarkdown($child));
                    $lines = $inner === '' ? [] : explode("\n", $inner);
                    $quoted = [];

                    foreach ($lines as $line)
                        $quoted[] = trim($line) === '' ? '>' : '> ' . rtrim($line);

                    $output .= implode("\n", $quoted) . "\n\n";
                    break;

                case 'a':
                    $href = trim($child->getAttribute('href'));
                    $label = trim(self::NodeToMarkdown($child));
                    $output .= $href !== '' && $label !== '' ? '[' . $label . '](' . $href . ')' : $label;
                    break;

                case 'img':
                    $src = trim($child->getAttribute('src'));
                    $alt = trim($child->getAttribute('alt'));

                    if ($src !== '')
                        $output .= '![' . $alt . '](' . $src . ')';
                    break;

                case 'ul':
                    $output .= self::ListToMarkdown($child, false) . "\n";
                    break;

                case 'ol':
                    $output .= self::ListToMarkdown($child, true) . "\n";
                    break;

                case 'li':
                    $output .= trim(self::NodeToMarkdown($child));
                    break;

                case 'hr':
                    $output .= "---\n\n";
                    break;

                default:
                    $output .= self::NodeToMarkdown($child);
            }
        }

        return $output;
    }

    /**
     * @param DOMElement $list
     */
    private static function ListToMarkdown(DOMElement $list, bool $ordered): string
    {
        $items = '';
        $index = 1;

        foreach ($list->childNodes as $child)
        {
            if (!$child instanceof DOMElement || strtolower($child->tagName) !== 'li')
                continue;

            $item = trim(self::NodeToMarkdown($child));
            $item = preg_replace("/\n{2,}/", "\n", $item) ?? $item;

            $lines = explode("\n", $item);
            $prefix = $ordered ? ($index++ . '. ') : '- ';

            $items .= $prefix . array_shift($lines);

            foreach ($lines as $line)
                $items .= "\n  " . $line;

            $items .= "\n";
        }

        return rtrim($items, "\n");
    }

    /**
     * Strip HTML when DOMDocument is unavailable.
     */
    private static function FromHtmlFallback(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? '';
        $text = preg_replace('/<\/(?:p|div|li|h[1-6])\s*>/i', "\n", $text) ?? '';
        $text = preg_replace('/<[^>]+>/', '', $text) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim($text);
    }
}
