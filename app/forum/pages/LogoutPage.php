<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\SessionManager;
use App\Classes\UrlManager;
use App\Classes\Database;

use App\Forum\Controllers\ViewingController;

use App\Interfaces\PageInterface;

class LogoutPage extends PageBase implements PageInterface
{
    public function __construct(Database $database, object $data)
    {
        parent::__construct($database, $data);
    }

    public function Do()
    {
        if(!SessionManager::IsLogged())
            UrlManager::Redirect($this->serverPath);

        ViewingController::Clear($this->database, (int) ($_SESSION['bitboard_user']->id ?? 0));

        SessionManager::Delete();
        UrlManager::Redirect($this->serverPath);
    }
}

?>