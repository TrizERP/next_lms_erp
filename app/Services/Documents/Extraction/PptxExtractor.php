<?php

namespace App\Services\Documents\Extraction;

use PhpOffice\PhpPresentation\IOFactory;
use Throwable;

class PptxExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        $ext = strtolower($extension);
        return $ext === 'pptx' || $ext === 'ppt' || str_contains($mimeType, 'presentation');
    }

    public function extract(string $filePath, string $mimeType): string
    {
        try {
            $presentation = IOFactory::load($filePath);
            $text = '';
            foreach ($presentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if (method_exists($shape, 'getText')) {
                        $text .= $shape->getText() . "\n";
                    } elseif (method_exists($shape, 'getParagraphs')) {
                        foreach ($shape->getParagraphs() as $p) {
                            $text .= $p->getText() . " ";
                        }
                        $text .= "\n";
                    }
                }
            }
            return trim($text);
        } catch (Throwable $e) {
            return '';
        }
    }
}
