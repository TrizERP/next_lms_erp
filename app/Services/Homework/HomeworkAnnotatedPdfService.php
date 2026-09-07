<?php

namespace App\Services\Homework;

use setasign\Fpdi\Fpdi;

/**
 * Produces `student_uploaded_evaluated.pdf` — the STUDENT'S OWN uploaded
 * pages (PDF or photo), unchanged, with evaluation marks drawn directly on
 * top: a green tick beside a correct answer, a red cross beside a wrong one
 * (plus a small "Correct answer: ..." note), an amber triangle for a
 * partially correct one, and a short AI remark where space allows.
 *
 * This deliberately does NOT typeset a fresh report document — a PDF
 * submission's original pages are imported as untouched templates via FPDI
 * (setasign/fpdi, already a project dependency) and only the overlay is
 * drawn; a photo submission becomes the page's full-size background image
 * with the same overlay drawn on top. Either way the original handwriting
 * and page appearance is preserved pixel-for-pixel.
 */
class HomeworkAnnotatedPdfService
{
    private const MARGIN = 24.0;
    private const MARK_SIZE = 12.0;

    /**
     * @param array<int, array{
     *   question_no:int, status:string, expected_answer:string, remarks:string,
     *   page:int, box_2d: array{0:int,1:int,2:int,3:int}|null
     * }> $annotations
     */
    public function annotate(string $originalAbsolutePath, string $mimeType, array $annotations): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetLineWidth(1.2);

        if ($mimeType === 'application/pdf') {
            $this->renderPdfSource($pdf, $originalAbsolutePath, $annotations);
        } else {
            $this->renderImageSource($pdf, $originalAbsolutePath, $mimeType, $annotations);
        }

        return $pdf->Output('S');
    }

    private function renderPdfSource(Fpdi $pdf, string $path, array $annotations): void
    {
        $pageCount = $pdf->setSourceFile($path);
        $byPage = $this->groupByPage($annotations, $pageCount);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $this->drawOverlaysForPage($pdf, $size['width'], $size['height'], $byPage[$pageNo] ?? []);
        }
    }

    private function renderImageSource(Fpdi $pdf, string $path, string $mimeType, array $annotations): void
    {
        $dimensions = @getimagesize($path);
        // Fall back to a standard photo aspect ratio if the file's real
        // dimensions can't be read — the page must still exist so the
        // upload isn't silently lost.
        $width = $dimensions[0] ?? 1000.0;
        $height = $dimensions[1] ?? 1400.0;

        $pdf->AddPage('P', [(float) $width, (float) $height]);
        $imageType = $mimeType === 'image/png' ? 'PNG' : 'JPG';
        $pdf->Image($path, 0, 0, (float) $width, (float) $height, $imageType);

        $byPage = $this->groupByPage($annotations, 1);
        $this->drawOverlaysForPage($pdf, (float) $width, (float) $height, $byPage[1] ?? []);
    }

    /** @return array<int, array<int, array<string,mixed>>> page number => annotations */
    private function groupByPage(array $annotations, int $pageCount): array
    {
        $grouped = [];
        foreach ($annotations as $annotation) {
            $page = max(1, min($pageCount, (int) ($annotation['page'] ?? 1)));
            $grouped[$page][] = $annotation;
        }

        return $grouped;
    }

    private function drawOverlaysForPage(Fpdi $pdf, float $pageWidth, float $pageHeight, array $annotations): void
    {
        $fallbackY = self::MARGIN;

        foreach ($annotations as $annotation) {
            $box = $annotation['box_2d'] ?? null;

            if ($box) {
                [$ymin, $xmin, $ymax, $xmax] = $box;
                $x0 = $xmin / 1000 * $pageWidth;
                $y0 = $ymin / 1000 * $pageHeight;
                $x1 = $xmax / 1000 * $pageWidth;
                $y1 = $ymax / 1000 * $pageHeight;

                $markX = $x1 + 6;
                $markY = $y0 + (($y1 - $y0) - self::MARK_SIZE) / 2;
                if ($markX + self::MARK_SIZE > $pageWidth - self::MARGIN) {
                    // No room to the right of the answer — drop the mark
                    // just below the line instead of running off the page.
                    $markX = $x0;
                    $markY = $y1 + 4;
                }
                $noteX = $markX + self::MARK_SIZE + 6;
                $noteY = $markY - 2;
                $noteWidth = max(0, $pageWidth - self::MARGIN - $noteX);
            } else {
                // No spatial grounding for this answer — stack it in the
                // right margin so it's still visible on the correct page
                // rather than dropping the mark entirely.
                $markX = $pageWidth - self::MARGIN - self::MARK_SIZE - 130;
                $markY = $fallbackY;
                $noteX = $markX + self::MARK_SIZE + 6;
                $noteY = $markY - 2;
                $noteWidth = 120;
                $fallbackY += 42;
            }

            $this->drawMark($pdf, $markX, $markY, (string) $annotation['status']);
            $this->drawNote($pdf, $noteX, $noteY, $noteWidth, $pageHeight, $annotation);
        }
    }

    private function drawMark(Fpdi $pdf, float $x, float $y, string $status): void
    {
        $size = self::MARK_SIZE;

        match ($status) {
            'correct' => $this->drawTick($pdf, $x, $y, $size),
            'partially_correct' => $this->drawTriangle($pdf, $x, $y, $size),
            default => $this->drawCross($pdf, $x, $y, $size),
        };
    }

    private function drawTick(Fpdi $pdf, float $x, float $y, float $size): void
    {
        $pdf->SetDrawColor(22, 163, 74); // green
        $pdf->Line($x, $y + $size * 0.55, $x + $size * 0.35, $y + $size);
        $pdf->Line($x + $size * 0.35, $y + $size, $x + $size, $y);
    }

    private function drawCross(Fpdi $pdf, float $x, float $y, float $size): void
    {
        $pdf->SetDrawColor(220, 38, 38); // red
        $pdf->Line($x, $y, $x + $size, $y + $size);
        $pdf->Line($x, $y + $size, $x + $size, $y);
    }

    private function drawTriangle(Fpdi $pdf, float $x, float $y, float $size): void
    {
        $pdf->SetDrawColor(217, 119, 6); // amber
        $pdf->Line($x + $size / 2, $y, $x, $y + $size);
        $pdf->Line($x, $y + $size, $x + $size, $y + $size);
        $pdf->Line($x + $size, $y + $size, $x + $size / 2, $y);
    }

    private function drawNote(Fpdi $pdf, float $x, float $y, float $width, float $pageHeight, array $annotation): void
    {
        if ($width < 40 || $y > $pageHeight - self::MARGIN) {
            return; // no room — the mark alone still communicates the verdict
        }

        $lines = [];
        if ($annotation['status'] === 'wrong' && trim((string) $annotation['expected_answer']) !== '') {
            $lines[] = 'Correct answer: ' . $this->truncate((string) $annotation['expected_answer'], 90);
        }
        if (trim((string) $annotation['remarks']) !== '') {
            $lines[] = $this->truncate((string) $annotation['remarks'], 90);
        }
        if (empty($lines)) {
            return;
        }

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(...($annotation['status'] === 'wrong' ? [185, 28, 28] : [75, 85, 99]));
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($width, 9, implode("\n", $lines), 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim($text);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
}
