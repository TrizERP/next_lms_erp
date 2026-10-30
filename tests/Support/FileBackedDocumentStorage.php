<?php

namespace Tests\Support;

use App\Services\Documents\DocumentStorageService;

/** Reads "stored" documents from a local folder instead of Spaces. Only get() is needed by the worker. */
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
}
