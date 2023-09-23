<?php

namespace App\Classes;

/**
 * The UrlManager class provides utility methods for managing URLs and HTTP redirects.
 */
class UrlManager
{
    /**
     * Refresh the current page after a specified time.
     *
     * @param int $time The time in seconds before the page should refresh.
     * @return bool True if the refresh header was set successfully, false otherwise.
     */
    static public function Refresh(int $time = 0): bool
    {
        header('Refresh:' . $time);
        return true;
    }

    /**
     * Redirect to a specified URL with optional query parameters.
     *
     * @param string $url    The URL to redirect to.
     * @param array  $params An associative array of query parameters (optional).
     * @return bool True if the redirect header was set successfully, false otherwise.
     */
    static public function Redirect(string $url = './', array $params = array()): bool
    {
        header('Location: ' . $url . (sizeof($params) > 0 ? ('?' . http_build_query($params)) : ''));
        return true;
    }

    /**
     * Get the base URL path for the current application.
     *
     * @return string The base URL path (e.g., "http://example.com/myapp").
     */
    static public function GetPath(): string
    {
        $protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];

        $directory = dirname($_SERVER['PHP_SELF']);
        $urlPath = $protocol . $host . $directory;

        return $urlPath;
    }
}

?>