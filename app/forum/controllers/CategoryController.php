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

    public static function GetAll(Database $db): array
    {
        return $db->Query('SELECT * FROM bit_categories ORDER BY category_position ASC, id ASC')->FetchAll();
    }

    public static function GetByID(Database $db, int $categoryID): array
    {
        return $db->Query('SELECT * FROM bit_categories WHERE id = ? LIMIT 1', $categoryID)->FetchArray();
    }

    public static function GetForumCounts(Database $db): array
    {
        $rows = $db->Query('SELECT category_id, COUNT(*) AS forum_count
            FROM bit_forums GROUP BY category_id')->FetchAll();

        $counts = [];
        foreach ($rows as $row)
            $counts[(int) $row['category_id']] = (int) $row['forum_count'];

        return $counts;
    }

    public static function CountForums(Database $db, int $categoryID): int
    {
        $row = $db->Query('SELECT COUNT(*) AS forum_count FROM bit_forums
            WHERE category_id = ?', $categoryID)->FetchArray();

        return (int) ($row['forum_count'] ?? 0);
    }

    public static function NextPosition(Database $db): int
    {
        $row = $db->Query('SELECT COALESCE(MAX(category_position), -1) + 1 AS next_position
            FROM bit_categories')->FetchArray();

        return (int) ($row['next_position'] ?? 0);
    }

    public static function Update(
        Database $db,
        int $categoryID,
        string $categoryName,
        string $categoryDescription,
        string $categoryIcon,
        int $categoryPosition
    ): void
    {
        $db->Query('UPDATE bit_categories
            SET category_name = ?, category_desc = ?, category_icon = ?, category_position = ?
            WHERE id = ?',
            $categoryName, $categoryDescription, $categoryIcon, $categoryPosition, $categoryID);
    }

    public static function Delete(Database $db, int $categoryID): void
    {
        $db->Query('DELETE FROM bit_categories WHERE id = ?', $categoryID);
    }
}

?>