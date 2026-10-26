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
}

?>
