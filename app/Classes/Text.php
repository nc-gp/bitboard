<?php

namespace App\Classes;

/**
 * Small text helpers used by search results, feeds, and metadata.
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
     * Convert plain text from a posting form into safe HTML paragraphs.
     *
     * @param string $text The submitted text.
     *
     * @return string Safe HTML.
     */
    /**
     * Convert stored HTML back into editable plain text.
     */
    public static function FromHtml(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? '';
        $text = preg_replace('/<\/p\s*>/i', "\n\n", $text) ?? '';
        $text = preg_replace('/<\/h[1-6]\s*>/i', "\n\n", $text) ?? '';
        $text = preg_replace('/<[^>]+>/', '', $text) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';

        return trim($text);
    }

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

            $paragraph = htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<p>' . nl2br($paragraph, false) . '</p>';
        }

        return $html;
    }
}

?>
