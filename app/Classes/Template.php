<?php

namespace App\Classes;

use Error;
use App\BB;

class Template
{
    public $template;
    private $fullPath;
    private string $name;
    private array $variables = [];
    private array $replaces = [];

    /**
     * Constructor to initialize the Template object with a template file path.
     *
     * @param string $theme         Current active theme name.
     * @param string $category_path Templates category.
     * @param string $name          Template file name without .html.
     */
    public function __construct(string $category_path, string $name, bool $custom = false)
    {
        $this->name = $name;

        if(!$custom)
            $this->fullPath = './themes/' . BB_THEME . '/templates/' . $category_path . '/' . $this->name . '.html';
        else
            $this->fullPath = $category_path . $this->name . '.html';

        $this->Load();
    }

    /**
     * Load the template content from the specified file.
     *
     * @throws Error if the template file does not exist.
     */
    private function Load()
    {
        if (!is_file($this->fullPath))
            throw new Error("Template file does not exist: " . $this->fullPath);

        $content = @file_get_contents($this->fullPath);
        if ($content === false)
            throw new Error("Unable to read template file: " . $this->fullPath);

        // Resolve the global base URL before any page content is injected.
        // Pagination uses its own route-specific placeholder instead.
        $content = str_replace('{server_url}', UrlManager::GetPath(), $content);

        $this->template = '<!-- start: ' . $this->name . ' -->';
        $this->template .= $content;
        $this->template .= '<!-- end: ' . $this->name . ' -->';
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
     * Add a single entry whose value is escaped for HTML text or attribute context.
     *
     * @param string $variable The name of the entry (placeholder).
     * @param mixed  $replace  The value to escape and replace the entry with.
     */
    public function AddEscapedEntry(string $variable, mixed $replace): void
    {
        $this->AddEntry($variable, self::Escape($replace));
    }

    /**
     * Add a single entry whose value is encoded for a JavaScript string context.
     *
     * @param string $variable The name of the entry (placeholder).
     * @param mixed  $replace  The value to encode and replace the entry with.
     */
    public function AddJsEntry(string $variable, mixed $replace): void
    {
        $this->AddEntry($variable, self::EncodeJs($replace));
    }

    /**
     * Escape a value for HTML text or attribute context.
     */
    public static function Escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Encode a value for a JavaScript string context.
     */
    public static function EncodeJs(mixed $value): string
    {
        $encoded = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $encoded === false ? 'null' : $encoded;
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

        if ($variablesSize !== $replacesSize)
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