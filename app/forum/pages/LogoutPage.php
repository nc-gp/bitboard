<?php

namespace App\Forum\Pages;

use App\Classes\SessionManager;
use App\Classes\UrlManager;

use App\Interfaces\PageInterface;

class LogoutPage implements PageInterface
{
    public function __construct($database, array $data)
    {
        $this->Do();
    }

    public function Do()
    {
        if(!SessionManager::IsLogged())
        {
            UrlManager::Redirect();
            return;
        }

        SessionManager::Delete();
        UrlManager::Redirect();
    }
}

?>