<?php

/**
 * The File class provides methods for working with files, such as reading, writing, and deleting.
 */
class File
{
    private string $path;
    public mixed $data;

    /**
     * Constructor to initialize a File object with a file path.
     *
     * @param string $path The path to the file.
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        $this->data = $this->_load();
    }

    /**
     * Update the data stored in the File object.
     *
     * @param mixed $data The data to update in the File object.
     */
    public function UpdateData(mixed $data): void
    {
        $this->data = $data;
    }

    /**
     * Save the data to the file.
     */
    public function Save(): void
    {
        file_put_contents($this->path, $this->data);
    }

    /**
     * Load data from the file into the File object.
     */
    public function Load(): void
    {
        if ($this->Exists())
            $data = file_get_contents($this->path);
    }

    /**
     * Check if the file exists.
     *
     * @return bool True if the file exists; false otherwise.
     */
    public function Exists(): bool
    {
        if (file_exists($this->path))
            return true;

        return false;
    }

    /**
     * Remove (delete) the file from the filesystem.
     */
    public function Remove(): void
    {
        unlink($this->path);
    }

    /**
     * Load data from the file or return -1 if the file does not exist.
     *
     * @return mixed The loaded data or -1 if the file does not exist.
     */
    private function _load(): mixed
    {
        if ($this->Exists())
            return file_get_contents($this->path);

        return -1;
    }
}

?>