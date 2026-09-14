<?php

namespace App\Services\Homework;

use App\Services\Homework\Exceptions\DocumentExtractionException;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extracts readable text from an assignment PDF (teacher-uploaded) or a
 * student submission file (PDF/JPG/JPEG/PNG/DOC/DOCX).
 *
 * Strategy:
 *  - PDF with a real text layer: parsed directly via smalot/pdfparser
 *    (fast, free, no external call).
 *  - PDF with little/no extractable text (i.e. scanned) or any image file:
 *    falls back to OCR via Gemini's multimodal vision input. This avoids a
 *    native Tesseract binary dependency (no such package/binary is
 *    installed in this project, and the target hosting cannot be assumed
 *    to have one) while satisfying the "Tesseract or equivalent" OCR
 *    requirement.
 *  - Word documents (.doc/.docx) already carry their own text layer, so
 *    they're read directly via phpoffice/phpword and never sent to Gemini
 *    at all — there is no scanned/image case for a native Word file.
 */
class HomeworkDocumentExtractionService
{
    /** Below this many characters, a "PDF text layer" is treated as absent (scanned page images, watermarks, etc). */
    private const MIN_TEXT_LAYER_LENGTH = 40;

    public const WORD_MIME_TYPES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /**
     * @param string $absolutePath Absolute filesystem path to the file.
     * @param string $mimeType One of application/pdf, image/jpeg, image/png,
     *                         application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document.
     * @param string $context Short label used in the OCR prompt, e.g. "assignment questions" or "student answers".
     *
     * @throws DocumentExtractionException
     */
    public function extractText(string $absolutePath, string $mimeType, string $context = 'document'): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new DocumentExtractionException("File not found or unreadable: {$absolutePath}");
        }

        if (in_array($mimeType, self::WORD_MIME_TYPES, true)) {
            return $this->extractWordText($absolutePath, $mimeType);
        }

        if ($mimeType === 'application/pdf') {
            $textLayer = $this->extractPdfTextLayer($absolutePath);
            if (mb_strlen(trim($textLayer)) >= self::MIN_TEXT_LAYER_LENGTH) {
                return trim($textLayer);
            }
        }

        return $this->extractViaGeminiOcr($absolutePath, $mimeType, $context);
    }

    /** @throws DocumentExtractionException */
    private function extractWordText(string $absolutePath, string $mimeType): string
    {
        try {
            $readerType = $mimeType === 'application/msword' ? 'MsDoc' : 'Word2007';
            $phpWord = WordIOFactory::load($absolutePath, $readerType);
        } catch (\Throwable $exception) {
            throw new DocumentExtractionException('The Word document could not be read: ' . $exception->getMessage(), 0, $exception);
        }

        $sections = [];
        foreach ($phpWord->getSections() as $section) {
            $sectionText = $this->collectElementsText($section->getElements());
            if ($sectionText !== '') {
                $sections[] = $sectionText;
            }
        }

        $combined = trim(implode("\n", $sections));
        if ($combined === '') {
            throw new DocumentExtractionException('The Word document has no readable text.');
        }

        return $combined;
    }

    /** @param array<int, mixed> $elements */
    private function collectElementsText(array $elements): string
    {
        $lines = [];
        foreach ($elements as $element) {
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $lines[] = $this->collectElementsText($cell->getElements());
                    }
                }
                continue;
            }

            if (method_exists($element, 'getElements')) {
                $lines[] = $this->collectElementsText($element->getElements());
                continue;
            }

            if (method_exists($element, 'getText')) {
                $text = $element->getText();
                if (is_string($text) && trim($text) !== '') {
                    $lines[] = $text;
                }
            }
        }

        return implode("\n", array_filter($lines, static fn ($line) => trim((string) $line) !== ''));
    }

    private function extractPdfTextLayer(string $absolutePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($absolutePath);

            return (string) $pdf->getText();
        } catch (\Throwable $exception) {
            // Malformed/encrypted PDF: fall through to OCR rather than failing outright.
            return '';
        }
    }

    private function extractViaGeminiOcr(string $absolutePath, string $mimeType, string $context): string
    {
        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false) {
            throw new DocumentExtractionException("Unable to read file for OCR: {$absolutePath}");
        }

        $prompt = "This file is a {$context} for a school homework assignment. "
            . "Transcribe every word exactly as written, preserving question numbers, "
            . "line breaks and structure. If the file contains handwriting, read it as "
            . "carefully as possible. Output only the transcribed text, no commentary.";

        try {
            $result = $this->gemini->generateContent([
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($bytes),
                    ],
                ],
            ], [
                'temperature' => 0.1,
                'maxOutputTokens' => 6000,
            ]);
        } catch (\Throwable $exception) {
            throw new DocumentExtractionException('OCR via Gemini failed: ' . $exception->getMessage(), 0, $exception);
        }

        $text = trim($result['text']);
        if ($text === '') {
            throw new DocumentExtractionException('OCR did not return any readable text.');
        }

        return $text;
    }
}
