<?php

namespace Tests\Support;

use App\Services\Documents\DocumentStorageService;

/** Reads "stored" documents from a local folder instead of Spaces. */
class FileBackedDocumentStorage extends DocumentStorageService
{
    public function __construct(private string $dir)
    {
    }

    public function get(string $path): string
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . basename($path);

        return is_file($file) ? (string) file_get_contents($file) : throw new \RuntimeException("No stored file {$path}");
    }

    public function readStream(string $path)
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . basename($path);
        $stream = is_file($file) ? fopen($file, 'rb') : false;

        return $stream !== false ? $stream : throw new \RuntimeException("No stored file {$path}");
    }
}
