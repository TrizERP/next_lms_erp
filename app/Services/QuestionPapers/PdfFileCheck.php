<?php

namespace App\Services\QuestionPapers;

/**
 * Checks a file is really a PDF by its content, not by the name or MIME type the browser sent.
 * Used at upload (reject early) and again in the worker (the stored bytes are what gets parsed).
 */
final class PdfFileCheck
{
    /** @return string|null why the file is unacceptable, or null when it looks like a whole PDF */
    public static function problem(string $path): ?string
    {
        $size = @filesize($path);
        if ($size === false || $size < 64) {
            return 'The file is empty or too small to be a PDF.';
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 'The file could not be read.';
        }

        try {
            $head = (string) fread($handle, 1024);
            if (!str_contains($head, '%PDF-')) {
                return 'The file is not a PDF (missing PDF signature).';
            }

            // A complete PDF ends with %%EOF; a truncated upload does not.
            fseek($handle, max(0, $size - 2048));
            $tail = (string) fread($handle, 2048);
            if (!str_contains($tail, '%%EOF')) {
                return 'The PDF looks truncated or corrupted (no end-of-file marker).';
            }
        } finally {
            fclose($handle);
        }

        return null;
    }
}
