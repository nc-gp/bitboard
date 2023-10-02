<?php

namespace App\Forum\Structs;

class ForumStruct
{
    /**
     * @var int The unique identifier of the forum.
     */
    public $id;

    /**
     * @var int The category ID to which the forum belongs.
     */
    public $category_id;

    /**
     * @var string The name of the forum.
     */
    public $forum_name;

    /**
     * @var string The description of the forum.
     */
    public $forum_desc;

    /**
     * @var string The icon associated with the forum.
     */
    public $forum_icon;

    /**
     * @var int The position of the forum in the category.
     */
    public $forum_position;

    /**
     * @var bool Indicates whether the forum is locked or not.
     */
    public $is_locked;

    /**
     * @var int The count of threads in the forum.
     * Note: Can be 0.
     */
    public $thread_count = 0;

    /**
     * @var int The count of posts in the forum.
     * Note: Can be 0.
     */
    public $post_count = 0;

    /**
     * @var array The array of subforums.
     * Note: Can be empty.
     */
    public $subforums = [];

    /**
     * ForumStruct constructor.
     *
     * @param array $forumData An associative array containing forum data.
     */
    public function __construct(array $forumData) 
    {
		foreach($forumData as $key => $value)
            $this->{$key} = $value;
    }
}

?>