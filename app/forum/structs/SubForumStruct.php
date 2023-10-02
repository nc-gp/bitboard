<?php

namespace App\Forum\Structs;

class SubForumStruct
{
    /**
     * @var int The unique identifier of the subforum.
     */
    public $id;

    /**
     * @var int The forum ID to which the subforum belongs.
     */
    public $forum_id;

    /**
     * @var string The name of the subforum.
     */
    public $subforum_name;

    /**
     * @var string The description of the subforum.
     */
    public $subforum_desc;

    /**
     * @var bool Indicates whether the subforum is locked or not.
     */
    public $is_locked;

    /**
     * SubForumStruct constructor.
     *
     * @param array $subforumData An associative array containing category data.
     */
    public function __construct(array $subforumData) 
    {
        foreach($subforumData as $key => $value)
            $this->{$key} = $value;
    }
}

?>