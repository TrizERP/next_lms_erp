<?php

namespace Tests\Feature\QuestionPapers;

/** Builds a small, structurally valid text PDF (one page per string) so the real parser can read it. */
final class TinyPdf
{
    /** @param list<string> $pages */
    public static function make(array $pages): string
    {
        $objects = [];
        $kids = [];
        $n = count($pages);

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pages as $i => $text) {
            $pageId = 4 + $i * 2;
            $contentId = $pageId + 1;
            $kids[] = "{$pageId} 0 R";
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
            $stream = "BT /F1 12 Tf 50 700 Td ({$escaped}) Tj ET";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count {$n} >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}
