<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class CategoryController
{
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_categories (
			id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			category_name VARCHAR(128) NOT NULL,
			category_desc VARCHAR(256) NOT NULL,
			category_icon VARCHAR(512) NOT NULL,
			category_position INT(6) NOT NULL
		)');
    }

    static public function Create(Database $db, string $categoryName, string $categoryDescription, string $categoryIcon = '', int $categoryPosition = 1)
    {
        $db->Query('INSERT INTO bit_categories 
            (id,category_name,category_desc,category_icon,category_position) 
            VALUES (?,?,?,?,?)',
            array(0, $categoryName, $categoryDescription, $categoryIcon, $categoryPosition)
        );
    }
}

?>