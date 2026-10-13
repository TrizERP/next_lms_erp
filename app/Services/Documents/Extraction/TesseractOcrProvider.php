<?php

namespace App\Services\Documents\Extraction;

class TesseractOcrProvider implements OcrProviderInterface
{
    public function isAvailable(): bool
    {
        // Check if tesseract CLI exists on the machine
        $res = @shell_exec('where tesseract 2>NUL');
        return !empty(trim((string)$res));
    }

    public function performOcr(string $filePath, array $languages = ['eng']): string
    {
        if (!$this->isAvailable()) {
            return '';
        }

        $langs = implode('+', $languages);
        $tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
        $cmd = sprintf('tesseract %s %s -l %s 2>NUL', escapeshellarg($filePath), escapeshellarg($tempOutput), escapeshellarg($langs));
        @shell_exec($cmd);

        $txtFile = $tempOutput . '.txt';
        if (file_exists($txtFile)) {
            $content = file_get_contents($txtFile);
            @unlink($txtFile);
            return trim($content ?: '');
        }

        return '';
    }
}
