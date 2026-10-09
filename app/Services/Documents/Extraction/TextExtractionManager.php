<?php

namespace App\Services\Documents\Extraction;

class TextExtractionManager
{
    /** @var DocumentExtractorInterface[] */
    protected array $extractors;
    protected OcrProviderInterface $ocr;

    public function __construct()
    {
        $this->extractors = [
            new PdfExtractor(),
            new DocxExtractor(),
            new ExcelExtractor(),
            new PptxExtractor(),
        ];

        // Prefer Tesseract if locally present; otherwise fallback to Cloud/Gemini OCR
        $tesseract = new TesseractOcrProvider();
        if ($tesseract->isAvailable()) {
            $this->ocr = $tesseract;
        } else {
            $this->ocr = new GeminiVisionOcrProvider();
        }
    }

    /**
     * Extract text from file path and mime type
     */
    public function extractText(string $filePath, string $mimeType, string $originalFileName): array
    {
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $extractedText = '';
        $usedOcr = false;

        // Try direct extractors first
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType, $extension)) {
                $extractedText = $extractor->extract($filePath, $mimeType);
                break;
            }
        }

        // If it is an image or a scanned PDF that yielded no text, invoke OCR
        $isImage = str_starts_with($mimeType, 'image/');
        $isPdf = $mimeType === 'application/pdf' || strtolower($extension) === 'pdf';

        if (($isImage || ($isPdf && strlen(trim($extractedText)) < 50)) && $this->ocr->isAvailable()) {
            $ocrText = $this->ocr->performOcr($filePath, config('idms.ocr_languages', ['eng', 'guj', 'hin']));
            if (strlen(trim($ocrText)) > strlen(trim($extractedText))) {
                $extractedText = $ocrText;
                $usedOcr = true;
            }
        }

        return [
            'text' => trim($extractedText),
            'used_ocr' => $usedOcr,
            'is_empty' => empty(trim($extractedText)),
        ];
    }
}
