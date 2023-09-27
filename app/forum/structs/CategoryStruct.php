<?php

namespace App\Forum\Structs;

class CategoryStruct
{
    public $id;
    public $name;
    public $icon;
    public $description;
    public $position;
    public $forums = [];

    public function __construct($id, $name, $icon, $description, $position) 
    {
        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->description = $description;
        $this->position = $position;
    }
}

?>