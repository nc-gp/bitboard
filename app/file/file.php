<?php

class File
{
    private string $path;
    public mixed $data;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->data = $this->_load();
    }

    public function UpdateData(mixed $data): void
    {
        $this->data = $data;
    }

    public function Save(): void
    {
        file_put_contents($this->path, $this->data);
    }

    public function Load(): void
    {
        if ($this->Exists())
            $data = file_get_contents($this->path);
    }

    public function Exists(): bool
    {
        if (file_exists($this->path))
            return true;

        return false;
    }

    public function Remove(): void
    {
        unlink($this->path);
    }

    private function _load(): mixed
    {
        if ($this->Exists())
            return file_get_contents($this->path);

        return -1;
    }
}

?>