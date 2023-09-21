<?php

/**
 * The Log class provides methods to log messages and errors to the browser console.
 */
class Log
{
    /**
     * Log a message to the browser console.
     *
     * @param string $message The message to log.
     */
    static public function Log($message)
    {
        if (is_array($message))
            $message = json_encode($message);
        else
            $message = "'$message'";

        echo '<script>console.log(' . $message . ')</script>';
    }

    /**
     * Log an error message to the browser console.
     *
     * @param string $message The error message to log.
     */
    static public function Error($message)
    {
        echo '<script>console.error("' . $message . '")</script>';
    }
}

?>