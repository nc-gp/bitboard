<?php

namespace App\Classes;

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
        // implement cache here

        if (class_exists($className)) {
            $page = new $className($database, $data);
            $page->Do();
            return $page;
        } else {
            $page = new \App\Forum\Pages\_404Page($database, $data);
            $page->Do();
            return $page;
        }
    }
}

?>