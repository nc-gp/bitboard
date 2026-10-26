<?php

namespace App\Classes;

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
     * Convert stored HTML back into editable plain text.
     */
    public static function FromHtml(string $html): string
    {
        $text = preg_replace_callback('/<blockquote\b[^>]*>(.*?)<\/blockquote>/is',
            static function (array $match): string
            {
                $inner = preg_replace('/<br\s*\/?>/i', "\n", $match[1]) ?? '';
                $inner = preg_replace('/<\/(?:p|div|li)\s*>/i', "\n", $inner) ?? '';
                $inner = preg_replace('/<[^>]+>/', '', $inner) ?? '';
                $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $inner = preg_replace("/[ \t]+\n/", "\n", $inner) ?? '';
                $inner = trim($inner);

                if ($inner === '')
                    return '';

                $lines = explode("\n", $inner);
                $quoted = [];

                foreach ($lines as $line)
                    $quoted[] = trim($line) === '' ? '>' : '> ' . rtrim($line);

                return "\n" . implode("\n", $quoted) . "\n";
            },
            $html
        ) ?? '';

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? '';
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? '';
        $text = preg_replace('/<\/h[1-6]\s*>/i', "\n\n", $text) ?? '';
        $text = preg_replace('/<[^>]+>/', '', $text) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';

        return trim($text);
    }

    /**
     * Convert plain text from a posting form into safe HTML paragraphs.
     *
     * Lines starting with ">" become a blockquote. A leading "name wrote:"
     * line is treated as the quote attribution and rendered in bold.
     *
     * @param string $text The submitted text.
     *
     * @return string Safe HTML.
     */
    public static function ToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($text === '')
            return '';

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
        $html = '';

        foreach ($paragraphs as $paragraph)
        {
            $paragraph = trim($paragraph);

            if ($paragraph === '')
                continue;

            $lines = explode("\n", $paragraph);

            if (self::IsQuoteParagraph($lines))
            {
                $quote = self::QuoteToHtml($lines);

                if ($quote !== '')
                    $html .= $quote;

                continue;
            }

            $paragraph = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<p>' . nl2br($paragraph, false) . '</p>';
        }

        return $html;
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
     * Check whether every line in a paragraph starts with a quote marker.
     */
    private static function IsQuoteParagraph(array $lines): bool
    {
        $found = false;

        foreach ($lines as $line)
        {
            if (trim($line) === '')
                continue;

            if (!str_starts_with(ltrim($line), '>'))
                return false;

            $found = true;
        }

        return $found;
    }

    /**
     * Convert quote-marker lines into a safe blockquote.
     */
    private static function QuoteToHtml(array $lines): string
    {
        $quoteLines = [];

        foreach ($lines as $line)
        {
            $line = preg_replace('/^(?:\s*>\s?)+/', '', $line) ?? '';
            $quoteLines[] = rtrim($line);
        }

        $author = '';

        if (count($quoteLines) > 0 && preg_match('/^.{1,64} wrote:$/u', $quoteLines[0]) === 1)
            $author = trim(array_shift($quoteLines));

        $body = trim(implode("\n", $quoteLines));

        if ($author === '' && $body === '')
            return '';

        $content = '';

        if ($author !== '')
            $content .= '<strong>' . htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';

        if ($body !== '')
        {
            if ($content !== '')
                $content .= '<br>';

            $content .= nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        return '<blockquote><p>' . $content . '</p></blockquote>';
    }
}

?>
