<?php

namespace App\Forum\Structs;

class CategoryStruct
{
    /**
     * @var int The unique identifier of the category.
     */
    public $id;

    /**
     * @var string The name of the category.
     */
    public $category_name;

    /**
     * @var string The description of the category.
     */
    public $category_desc;

    /**
     * @var string The icon associated with the category.
     */
    public $category_icon;

    /**
     * @var int The position of the category.
     */
    public $category_position;

    /**
     * @var array The array of forums.
     * Note: Can be empty.
     */
    public $forums = [];

    /**
     * CategoryStruct constructor.
     *
     * @param array $categoryData An associative array containing category data.
     */
    public function __construct(array $categoryData) 
    {
		foreach($categoryData as $key => $value)
            $this->{$key} = $value;
    }
}

?>