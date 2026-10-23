<?php

namespace App\Classes;

/**
 * The Log class provides methods to log messages and errors to the browser console.
 */
class Console
{
    /**
     * Log a message to the browser console.
     *
     * @param mixed $message The message to log.
     */
    static public function Log(mixed $message): void
    {
        $encoded = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($encoded === false)
            $encoded = 'null';

        echo '<script>console.log(' . $encoded . ');</script>';
    }

    /**
     * Log an error message to the browser console.
     *
     * @param mixed $message The error message to log.
     */
    static public function Error(mixed $message): void
    {
        $encoded = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($encoded === false)
            $encoded = '"Error"';

        echo '<script>console.error(' . $encoded . ');</script>';
    }
}

?>