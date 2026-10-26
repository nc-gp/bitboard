<?php

namespace App\Classes;

/**
 * Turns thread titles into safe, readable URL slugs.
 */
class Slug
{
    /**
     * Build a lowercase ASCII slug from a thread title.
     *
     * Slugs are generated from the current title instead of being stored, so a
     * renamed thread automatically gets a new canonical URL while old slugs can
     * redirect to it.
     *
     * @param string $title     The thread title.
     * @param int    $maxLength The maximum slug length.
     *
     * @return string The generated slug.
     */
    public static function FromTitle(string $title, int $maxLength = 80): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = str_replace(["'", "\xE2\x80\x99"], '', $title);

        if (function_exists('iconv'))
        {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
            if ($converted !== false)
                $title = $converted;
        }

        // Transliteration can introduce apostrophes for accented characters.
        $title = str_replace(["'", '`'], '', $title);
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?? '';
        $title = trim($title, '-');

        if ($title === '')
            $title = 'thread';

        if (strlen($title) > $maxLength)
            $title = rtrim(substr($title, 0, $maxLength), '-');

        return $title === '' ? 'thread' : $title;
    }
}

?>
