<?php

namespace App\Services\PAL\Reporting;

/**
 * Server-side chart rendering for the PAL Activity Report's .docx export,
 * using GD (built into PHP, already enabled on this install — confirmed via
 * `php -m | grep gd` before writing this) rather than a new composer
 * dependency. Mirrors, in PNG form, the same two visuals the web report
 * shows with recharts: a stacked "Mastery by Chapter" bar and a "Diagnostic
 * Performance Over Time" line.
 *
 * WHY A RAW STRING, NOT A TEMP FILE
 * Each method returns the PNG as a raw binary string plus its true pixel
 * size, not a file path. PhpWord\Element\Image::setSourceType() resolves any
 * source string containing a null byte — which real PNG bytes always do — to
 * SOURCE_STRING and decodes it with imagecreatefromstring(), so
 * Section::addImage() can take this output directly. That avoids a second
 * tempnam()/unlink() pair alongside the one StudentActivityReportDocxService
 * already uses for the finished .docx.
 *
 * WHY THE PIXEL SIZE IS RETURNED TOO
 * The caller sets a fixed display width in the document and needs the true
 * height to scale by, or the image would stretch. Mastery charts also have a
 * variable height (one row per chapter), so there is no single constant to
 * assume.
 */
class StudentActivityChartRenderer
{
    private const WIDTH = 820;

    private const COLOR_MASTERED = [22, 163, 74];
    private const COLOR_LEARNING = [59, 130, 246];
    private const COLOR_UNSEEN = [203, 213, 225];
    private const COLOR_GRID = [226, 232, 240];
    private const COLOR_TEXT = [30, 41, 59];
    private const COLOR_AXIS = [100, 116, 139];
    private const COLOR_LINE = [79, 70, 229];

    /**
     * Stacked horizontal bars — mastered/learning/unseen counts per chapter.
     * Capped at 10 chapters so the image stays legible; the docx table next
     * to it still lists every chapter.
     *
     * @param  list<array<string,mixed>>  $byChapter
     * @return array{binary:string,width:int,height:int}|null
     */
    public function masteryByChapterChart(array $byChapter): ?array
    {
        $rows = array_values(array_filter($byChapter, fn ($c) => (int) ($c['nodes_total'] ?? 0) > 0));
        if ($rows === []) {
            return null;
        }
        $rows = array_slice($rows, 0, 10);

        $rowHeight = 34;
        $topPad = 46;
        $bottomPad = 20;
        $leftPad = 190;
        $rightPad = 30;
        $width = self::WIDTH;
        $height = $topPad + $bottomPad + count($rows) * $rowHeight;

        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));

        $mastered = imagecolorallocate($im, ...self::COLOR_MASTERED);
        $learning = imagecolorallocate($im, ...self::COLOR_LEARNING);
        $unseen = imagecolorallocate($im, ...self::COLOR_UNSEEN);
        $grid = imagecolorallocate($im, ...self::COLOR_GRID);
        $text = imagecolorallocate($im, ...self::COLOR_TEXT);
        $axis = imagecolorallocate($im, ...self::COLOR_AXIS);

        $this->legendSwatch($im, $leftPad, 14, $mastered, 'Mastered', $text);
        $this->legendSwatch($im, $leftPad + 150, 14, $learning, 'Learning', $text);
        $this->legendSwatch($im, $leftPad + 300, 14, $unseen, 'Unseen', $text);

        $chartLeft = $leftPad;
        $chartRight = $width - $rightPad;
        $chartWidth = $chartRight - $chartLeft;
        $chartBottom = $topPad + count($rows) * $rowHeight;

        $maxTotal = max(1, max(array_map(fn ($r) => (int) ($r['nodes_total'] ?? 0), $rows)));

        for ($f = 0; $f <= 1.0001; $f += 0.25) {
            $x = (int) round($chartLeft + $f * $chartWidth);
            imageline($im, $x, $topPad, $x, $chartBottom, $grid);
        }

        foreach ($rows as $i => $row) {
            $y = $topPad + $i * $rowHeight;
            $barTop = $y + 6;
            $barBottom = $y + $rowHeight - 10;

            $label = (string) ($row['chapter_name'] ?? 'Unassigned');
            $label = mb_strlen($label) > 26 ? mb_substr($label, 0, 25).'…' : $label;
            imagestring($im, 3, 4, $barTop, $label, $text);

            $x = $chartLeft;
            foreach ([
                [(int) ($row['mastered'] ?? 0), $mastered],
                [(int) ($row['learning'] ?? 0), $learning],
                [(int) ($row['unseen'] ?? 0), $unseen],
            ] as [$count, $color]) {
                if ($count <= 0) {
                    continue;
                }
                $segWidth = max(1, (int) round(($count / $maxTotal) * $chartWidth));
                imagefilledrectangle($im, $x, $barTop, $x + $segWidth - 1, $barBottom, $color);
                $x += $segWidth;
            }
            imagerectangle($im, $chartLeft, $barTop, $chartRight, $barBottom, $axis);
        }

        return $this->flatten($im, $width, $height);
    }

    /**
     * A line chart of diagnostic percentage over time.
     *
     * @param  list<array{date?:string,percentage?:float}>  $trend
     * @return array{binary:string,width:int,height:int}|null
     */
    public function diagnosticTrendChart(array $trend): ?array
    {
        $points = array_values(array_filter($trend, fn ($t) => isset($t['date'], $t['percentage'])));
        if ($points === []) {
            return null;
        }

        $width = self::WIDTH;
        $height = 320;
        $topPad = 20;
        $bottomPad = 50;
        $leftPad = 50;
        $rightPad = 20;

        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));

        $grid = imagecolorallocate($im, ...self::COLOR_GRID);
        $text = imagecolorallocate($im, ...self::COLOR_TEXT);
        $axis = imagecolorallocate($im, ...self::COLOR_AXIS);
        $line = imagecolorallocate($im, ...self::COLOR_LINE);
        $dotFill = imagecolorallocate($im, 255, 255, 255);

        $chartLeft = $leftPad;
        $chartRight = $width - $rightPad;
        $chartTop = $topPad;
        $chartBottom = $height - $bottomPad;
        $chartWidth = $chartRight - $chartLeft;
        $chartHeight = $chartBottom - $chartTop;

        for ($p = 0; $p <= 100; $p += 25) {
            $y = (int) round($chartBottom - ($p / 100) * $chartHeight);
            imageline($im, $chartLeft, $y, $chartRight, $y, $grid);
            imagestring($im, 2, 4, $y - 7, $p.'%', $axis);
        }

        $n = count($points);
        $stepX = $n > 1 ? $chartWidth / ($n - 1) : 0;

        $coords = [];
        foreach ($points as $i => $point) {
            $x = $n > 1 ? (int) round($chartLeft + $i * $stepX) : (int) round(($chartLeft + $chartRight) / 2);
            $pct = max(0.0, min(100.0, (float) $point['percentage']));
            $y = (int) round($chartBottom - ($pct / 100) * $chartHeight);
            $coords[] = [$x, $y];
        }

        imagesetthickness($im, 2);
        for ($i = 1; $i < count($coords); $i++) {
            imageline($im, $coords[$i - 1][0], $coords[$i - 1][1], $coords[$i][0], $coords[$i][1], $line);
        }
        imagesetthickness($im, 1);

        $labelEvery = $n <= 8 ? 1 : (int) ceil($n / 8);
        foreach ($coords as $i => [$x, $y]) {
            imagefilledellipse($im, $x, $y, 8, 8, $line);
            imagefilledellipse($im, $x, $y, 4, 4, $dotFill);

            if ($i % $labelEvery === 0) {
                $label = (string) ($points[$i]['date'] ?? '');
                $label = strlen($label) > 5 ? substr($label, 5) : $label; // drop the leading "YYYY-"
                imagestring($im, 2, max(0, $x - (strlen($label) * 3)), $chartBottom + 8, $label, $text);
            }
        }

        imagerectangle($im, $chartLeft, $chartTop, $chartRight, $chartBottom, $axis);

        return $this->flatten($im, $width, $height);
    }

    private function legendSwatch($im, int $x, int $y, int $color, string $label, int $textColor): void
    {
        imagefilledrectangle($im, $x, $y, $x + 12, $y + 12, $color);
        imagestring($im, 2, $x + 18, $y, $label, $textColor);
    }

    /** @return array{binary:string,width:int,height:int} */
    private function flatten($im, int $width, int $height): array
    {
        ob_start();
        imagepng($im);
        $binary = (string) ob_get_clean();
        imagedestroy($im);

        return ['binary' => $binary, 'width' => $width, 'height' => $height];
    }
}
