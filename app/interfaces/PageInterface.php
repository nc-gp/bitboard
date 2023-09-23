<?php

namespace App\Interfaces;

use App\Classes\Database;

interface PageInterface 
{
    public function __construct(Database $database, array $data);
    public function Do();
}

?>