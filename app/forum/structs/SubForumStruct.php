<?php

namespace App\Forum\Structs;

class SubForumStruct
{
    public $id;
    public $name;
    public $description;
    public $isLocked;

    public function __construct($id, $name, $description, $isLocked) 
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->isLocked = $isLocked;
    }
}

?>