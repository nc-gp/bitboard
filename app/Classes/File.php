<?php

namespace App\Classes;

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
        $this->data = $this->Load();
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
     * Save the data to the file using fopen.
     */
    public function Save(): void
    {
        $fileHandle = fopen($this->path, 'w');
        if ($fileHandle) {
            fwrite($fileHandle, $this->data);
            fclose($fileHandle);
        }
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
    private function Load(): mixed
    {
        if ($this->Exists()) {
            $fileHandle = fopen($this->path, 'r');
            if ($fileHandle) {
                $data = fread($fileHandle, filesize($this->path));
                fclose($fileHandle);
                return $data;
            }
        }

        return -1;
    }
}

?>