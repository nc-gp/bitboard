<?php

namespace App\Forum\Structs;

class ForumStruct
{
    public $id;
    public $name;
    public $icon;
    public $description;
    public $position;
    public $isLocked;
    public $threadCount;
    public $postCount;
    public $subforums = [];

    public function __construct($id, $name, $icon, $description, $position, $isLocked, $threadCount, $postCount) 
    {
        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->description = $description;
        $this->position = $position;
        $this->isLocked = $isLocked;
        $this->threadCount = $threadCount;
        $this->postCount = $postCount;
    }
}

?>