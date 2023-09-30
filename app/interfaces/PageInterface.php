<?php

namespace App\Interfaces;

use App\Classes\Database;

interface PageInterface 
{
    public function __construct(Database $database, object $data);
    public function Do();
}

?>