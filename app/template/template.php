<?php

/**
 * The Template class provides functionality for loading, rendering, and replacing placeholders
 * in a template file.
 */
class Template
{
    public string $templ;
    private string $path;
    private array $entries = [];
    private array $replaces = [];

    /**
     * Constructor to initialize the Template object with a template file path.
     *
     * @param string $path The path to the template file.
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->Load();
    }

    /**
     * Render the template, optionally replacing placeholders with values.
     *
     * @param bool $replace Set to true to replace placeholders with values.
     */
    public function Render(bool $replace = false): void
    {
        if ($replace) {
            $this->Replace();
        }

        echo $this->templ;
    }

    /**
     * Add a single entry and its corresponding replacement value.
     *
     * @param string $entryName The name of the entry (placeholder).
     * @param mixed $replace    The value to replace the entry with.
     */
    public function AddEntry(string $entryName, $replace): void
    {
        $this->entries[] = $entryName;
        $this->replaces[] = $replace;
    }

    /**
     * Add multiple entries and their corresponding replacement values.
     *
     * @param array $entries   An array of entry names (placeholders).
     * @param array $replaces  An array of replacement values.
     * @throws Error if the count of entries does not match the count of replacements.
     */
    public function AddEntries(array $entries = [], array $replaces = []): void
    {
        $entriesSize = count($entries);
        $replacesSize = count($replaces);

        if ($entriesSize !== $replacesSize) {
            throw new Error("The entries size needs to be the same as replaces.");
        }

        for ($i = 0; $i < $entriesSize; $i++) {
            $this->entries[] = $entries[$i];
            $this->replaces[] = $replaces[$i];
        }
    }

    /**
     * Load the template content from the specified file.
     *
     * @throws Error if the template file does not exist.
     */
    private function Load(): void
    {
        if (!file_exists($this->path)) {
            throw new Error("Template file does not exist: " . $this->path);
        }

        $handle = fopen($this->path, 'r');
        if ($handle) {
            if (flock($handle, LOCK_SH)) {
                $this->templ = fread($handle, filesize($this->path));
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    /**
     * Replace placeholders in the template content with their corresponding values.
     */
    public function Replace(): void
    {
        $this->templ = str_replace($this->entries, $this->replaces, $this->templ);
    }
}

?>