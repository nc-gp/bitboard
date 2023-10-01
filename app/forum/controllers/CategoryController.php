<?php

namespace App\Forum\Controllers;

use App\Classes\Database;

class CategoryController
{
    /**
     * CreateTable creates the 'bit_categories' table in the database if it does not already exist.
     *
     * @param Database db The database instance.
     *
     * @return void
     */
    static public function CreateTable(Database $db)
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_categories (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_name VARCHAR(128) NOT NULL, category_desc VARCHAR(256) NOT NULL, category_icon VARCHAR(512) NOT NULL, category_position INT(6) NOT NULL)');
    }

    /**
     * Create inserts a new category record into the 'bit_categories' table.
     *
     * @param Database  db The database instance.
     * @param string    categoryName The name of the category.
     * @param string    categoryDescription The description of the category.
     * @param string    categoryIcon The icon URL or path for the category (default is an empty string).
     * @param int       categoryPosition The position of the category (default is 1).
     *
     * @return void
     */
    static public function Create(Database $db, string $categoryName, string $categoryDescription, string $categoryIcon = '', int $categoryPosition = 1)
    {
        $db->Query('INSERT INTO bit_categories (id,category_name,category_desc,category_icon,category_position) VALUES (?,?,?,?,?)', array(0, $categoryName, $categoryDescription, $categoryIcon, $categoryPosition));
    }
}

?>