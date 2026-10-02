<?php

namespace App\Services\Documents\Extraction;

interface OcrProviderInterface
{
    /**
     * Check if this OCR provider is configured and available
     */
    public function isAvailable(): bool;

    /**
     * Run OCR on the given image/PDF file
     */
    public function performOcr(string $filePath, array $languages = ['eng']): string;
}
