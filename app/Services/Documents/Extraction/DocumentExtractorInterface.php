<?php

namespace App\Services\Documents\Extraction;

interface DocumentExtractorInterface
{
    /**
     * Check if this extractor supports the given mime type or file extension
     */
    public function supports(string $mimeType, string $extension): bool;

    /**
     * Extract text content from the file stream or local temporary path
     */
    public function extract(string $filePath, string $mimeType): string;
}
