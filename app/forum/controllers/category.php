<?php

require_once './app/helpers/log.php';

class CategoryController
{
    public array $Data;
    public bool $IsGood = true;
    private int $ID;

    public function __construct($db, int $id)
    {
        $this->ID = $id;
        $this->Data = $db->Query('SELECT * FROM bit_categories WHERE id = ?', $this->ID)->FetchArray();

        if(sizeof($this->Data) <= 0)
        {
            $this->IsGood = false;
            Log::Error('Couldn\'t fetch category with ID ' . $this->ID);
        }
    }

    public function UpdateName($db, string $newCategoryName)
    {
        $db->Query('UPDATE bit_categories SET category_name = ? WHERE id = ?', $newCategoryName, $this->ID);
    }

    public function UpdateDescription($db, string $newCategoryDescription)
    {
        $db->Query('UPDATE bit_categories SET category_desc = ? WHERE id = ?', $newCategoryDescription, $this->ID);
    }

    public function UpdateIcon($db, string $newCategoryIcon)
    {
        $db->Query('UPDATE bit_categories SET category_icon = ? WHERE id = ?', $newCategoryIcon, $this->ID);
    }

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