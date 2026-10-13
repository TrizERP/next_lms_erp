<?php

namespace App\Services\Documents\Extraction;

use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/pdf' || strtolower($extension) === 'pdf';
    }

    public function extract(string $filePath, string $mimeType): string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            return trim($text ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }
}
