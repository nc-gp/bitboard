<?php

namespace App\Classes;

use Error;
use App\BB;

class Template
{
    public $template;
    private $fullPath;
    private array $variables;
    private array $replaces;

    /**
     * Constructor to initialize the Template object with a template file path.
     *
     * @param string $theme         Current active theme name.
     * @param string $category_path Templates category.
     * @param string $name          Template file name without .html.
     */
    public function __construct(string $category_path, string $name, bool $custom = false)
    {
        if(!$custom)
            $this->fullPath = './themes/' . BB::$Data['forum_theme'] . '/templates/' . $category_path . '/' . $name . '.html';
        else
            $this->fullPath = $category_path . $name . '.html';

        $this->Load();
    }

    /**
     * Load the template content from the specified file.
     *
     * @throws Error if the template file does not exist.
     */
    private function Load()
    {
        if (!file_exists($this->fullPath))
            throw new Error("Template file does not exist: " . $this->fullPath);

        $handle = fopen($this->fullPath, 'r');
        if ($handle) 
        {
            if (flock($handle, LOCK_SH)) 
            {
                $this->template = fread($handle, filesize($this->fullPath));
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }

    /**
     * Add a single entry and its corresponding replacement value.
     *
     * @param string $variable  The name of the entry (placeholder).
     * @param mixed $replace    The value to replace the entry with.
     */
    public function AddEntry(string $variable, $replace): void
    {
        $this->variables[] = $variable;
        $this->replaces[] = $replace;
    }

    /**
     * Add multiple entries and their corresponding replacement values.
     *
     * @param array $variables   An array of entry names (placeholders).
     * @param array $replaces    An array of replacement values.
     * @throws Error if the count of entries does not match the count of replacements.
     */
    public function AddEntries(array $variables = [], array $replaces = []): void
    {
        $variablesSize = count($variables);
        $replacesSize = count($replaces);

        if ($variables !== $replacesSize)
            throw new Error("The entries size needs to be the same as replaces.");

        for ($i = 0; $i < $variablesSize; $i++) {
            $this->variables[] = $variables[$i];
            $this->replaces[] = $replaces[$i];
        }
    }

    /**
     * Render the template, optionally replacing placeholders with values.
     *
     * @param bool $replace Set to true to replace placeholders with values.
     */
    public function Render(bool $replace = false): void
    {
        if ($replace)
            $this->Replace();

        echo $this->template;
    }

    /**
     * Replace placeholders in the template content with their corresponding values.
     */
    public function Replace(): void
    {
        $this->template = str_replace($this->variables, $this->replaces, $this->template);
    }
}

?>