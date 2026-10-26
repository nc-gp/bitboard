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
