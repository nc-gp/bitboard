<?php

namespace App\Classes;

use Exception;

class PageFactory 
{
    /**
     * Create a page instance based on the given page name.
     *
     * @param string $pageName  The name of the page to create.
     * @param mixed $database   The database connection or data needed for the page.
     * @param mixed $data       Additional data to pass to the page.
     *
     * @return object|null      An instance of the created page class.
     */
    public static function CreatePage($pageName, $database, $data) 
    {
        $className = 'App\Forum\Pages\\' . ucfirst($pageName) . 'Page';

        if (class_exists($className)) {
            $page = new $className($database, $data);
            $page->Do();
            echo 1;
            return $page;
        } else {
            throw new Exception("Page '$pageName' not found.");
        }
    }
}

?>