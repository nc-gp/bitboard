<?php

namespace App\Classes;

/**
 * XML escaping helpers for sitemap and feed output.
 */
class Xml
{
    /**
     * Escape a value for XML text or attribute context.
     */
    public static function Escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

?>
