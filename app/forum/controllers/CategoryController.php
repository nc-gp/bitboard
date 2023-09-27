<?php

namespace App\Forum\Controllers;

class CategoryController
{
    static public function Create($db, string $categoryName, string $categoryDescription, string $categoryIcon = '', int $categoryPosition = 1)
    {
        $db->Query('INSERT INTO bit_categories 
            (id,category_name,category_desc,category_icon,category_position) 
            VALUES (?,?,?,?,?)',
            array(0, $categoryName, $categoryDescription, $categoryIcon, $categoryPosition)
        );
    }
}

?>