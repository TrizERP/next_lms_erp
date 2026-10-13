<?php

namespace App\Services\Documents\Extraction;

use ZipArchive;
use Throwable;

class DocxExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        $ext = strtolower($extension);
        return $ext === 'docx' || $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extract(string $filePath, string $mimeType): string
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    // Extract plain text from Word XML tags
                    $clean = strip_tags(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml));
                    return trim(html_entity_decode($clean, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
            }
        } catch (Throwable $e) {
            // Fallback
        }
        return '';
    }
}
