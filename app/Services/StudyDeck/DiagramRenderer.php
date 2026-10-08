<?php

namespace App\Services\StudyDeck;

/**
 * Draws a simple educational diagram from a spec, with GD. No AI image generation.
 *
 * Used when a slide needs a visual that no openly licensed photograph can supply:
 * a chain of reasoning, a comparison, a set of ideas around one centre. The model
 * writes only the SPEC (a layout and short labels taken from the chapter); the
 * picture is drawn by code, so
 *   - every word in it is one the spec named, and the grounding check can read it;
 *   - the alt text is generated from the same spec and is true by construction;
 *   - it needs no source, licence or credit, because nothing was found.
 *
 * Layouts
 *   flow    nodes: 2-6 labels, drawn in order with arrows
 *   compare left/right: {heading, items[]}
 *   hub     center + nodes: 2-6 labels around one idea
 *
 * Colours are the design system's semantic tokens (indigo action, slate neutrals),
 * written once here because GD needs numbers; they are not new colours.
 */
class DiagramRenderer
{
    public const LAYOUTS = ['flow', 'compare', 'hub'];

    private const W = 1280;
    private const H = 720;

    // EduERP tokens: --action-primary, --surface-subtle, --border-subtle, --content-primary, --content-secondary.
    private const INDIGO = [79, 70, 229];
    private const INDIGO_SOFT = [238, 242, 255];
    private const SLATE_BORDER = [203, 213, 225];
    private const INK = [15, 23, 42];
    private const MUTED = [71, 85, 105];
    private const WHITE = [255, 255, 255];

    /** @return array<int,string> problems with a spec; empty when it can be drawn */
    public static function problems(array $spec): array
    {
        $out = [];
        $layout = $spec['layout'] ?? '';
        if (!in_array($layout, self::LAYOUTS, true)) {
            return ['layout must be one of ' . implode(', ', self::LAYOUTS)];
        }
        if (trim((string) ($spec['title'] ?? '')) === '') {
            $out[] = 'a diagram needs a title';
        }

        $labels = static fn (array $xs) => array_values(array_filter(array_map(fn ($x) => trim((string) (is_array($x) ? ($x['text'] ?? '') : $x)), $xs)));

        if ($layout === 'compare') {
            foreach (['left', 'right'] as $side) {
                $col = (array) ($spec[$side] ?? []);
                if (trim((string) ($col['heading'] ?? '')) === '' || count($labels((array) ($col['items'] ?? []))) < 1 || count((array) ($col['items'] ?? [])) > 5) {
                    $out[] = "compare needs a heading and 1-5 items on the $side";
                }
            }
        } else {
            $n = count($labels((array) ($spec['nodes'] ?? [])));
            if ($n < 2 || $n > 6) {
                $out[] = "$layout needs 2-6 nodes";
            }
            if ($layout === 'hub' && trim((string) ($spec['center'] ?? '')) === '') {
                $out[] = 'hub needs a centre';
            }
        }
        foreach (self::texts($spec) as $t) {
            if (mb_strlen($t) > 70) {
                $out[] = 'a label is longer than 70 characters: "' . mb_substr($t, 0, 30) . '..."';
            }
        }

        return $out;
    }

    /** Every piece of text a diagram will show, in reading order. */
    public static function texts(array $spec): array
    {
        $t = [trim((string) ($spec['title'] ?? ''))];
        if (($spec['layout'] ?? '') === 'compare') {
            foreach (['left', 'right'] as $side) {
                $t[] = trim((string) ($spec[$side]['heading'] ?? ''));
                foreach ((array) ($spec[$side]['items'] ?? []) as $i) {
                    $t[] = trim((string) $i);
                }
            }
        } else {
            if (($spec['layout'] ?? '') === 'hub') {
                $t[] = trim((string) ($spec['center'] ?? ''));
            }
            foreach ((array) ($spec['nodes'] ?? []) as $n) {
                $t[] = trim((string) (is_array($n) ? ($n['text'] ?? '') : $n));
            }
        }

        return array_values(array_filter($t, fn ($x) => $x !== ''));
    }

    /** Alt text built from the spec alone, so it can only describe what was drawn. */
    public static function describe(array $spec): string
    {
        $title = rtrim(trim((string) $spec['title']), '.');
        $nodes = array_map(fn ($n) => trim((string) (is_array($n) ? ($n['text'] ?? '') : $n)), (array) ($spec['nodes'] ?? []));

        return match ($spec['layout']) {
            'flow' => "Diagram: $title. Steps in order: " . implode(', then ', $nodes) . '.',
            'hub' => "Diagram: $title. '" . trim((string) $spec['center']) . "' connected to " . implode(', ', $nodes) . '.',
            'compare' => "Diagram: $title. " . trim((string) $spec['left']['heading']) . ': ' . implode('; ', array_map('trim', (array) $spec['left']['items']))
                . '. ' . trim((string) $spec['right']['heading']) . ': ' . implode('; ', array_map('trim', (array) $spec['right']['items'])) . '.',
        };
    }

    /** @return array{bytes:string,mime:string,width:int,height:int} */
    public function render(array $spec): array
    {
        if ($p = self::problems($spec)) {
            throw new \InvalidArgumentException('Diagram spec invalid: ' . implode('; ', $p));
        }
        $font = $this->font();
        $im = imagecreatetruecolor(self::W, self::H);
        imageantialias($im, true);
        imagefill($im, 0, 0, $this->c($im, self::WHITE));

        $this->text($im, $font, 34, self::INK, 60, 70, (string) $spec['title'], self::W - 120, true);
        imagefilledrectangle($im, 60, 96, 180, 100, $this->c($im, self::INDIGO));

        match ($spec['layout']) {
            'flow' => $this->flow($im, $font, array_map(fn ($n) => is_array($n) ? $n['text'] : $n, (array) $spec['nodes'])),
            'hub' => $this->hub($im, $font, (string) $spec['center'], array_map(fn ($n) => is_array($n) ? $n['text'] : $n, (array) $spec['nodes'])),
            'compare' => $this->compare($im, $font, $spec),
        };

        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return ['bytes' => $bytes, 'mime' => 'image/png', 'width' => self::W, 'height' => self::H];
    }

    private function flow($im, string $font, array $nodes): void
    {
        $nodes = array_values(array_map('trim', $nodes));
        $perRow = count($nodes) <= 4 ? count($nodes) : (int) ceil(count($nodes) / 2);
        $rows = (int) ceil(count($nodes) / $perRow);
        $gap = 54;
        $bw = (int) ((self::W - 120 - $gap * ($perRow - 1)) / $perRow);
        $bh = $rows === 1 ? 190 : 150;
        $top = $rows === 1 ? 280 : 200;
        $last = count($nodes) - 1;

        // Where a node sits. Rows snake: the second row runs right to left, so the arrow that
        // leaves the end of one row lands directly on the first box of the next.
        $place = function (int $i) use ($perRow, $bw, $gap, $bh, $top): array {
            $row = intdiv($i, $perRow);
            $col = $i % $perRow;
            $slot = $row % 2 === 0 ? $col : $perRow - 1 - $col;

            return [60 + $slot * ($bw + $gap), $top + $row * ($bh + 70), $row, $col];
        };

        foreach ($nodes as $i => $label) {
            [$x, $y, $row, $col] = $place($i);
            $this->box($im, $font, $x, $y, $bw, $bh, $label, $i === 0 || $i === $last);
            if ($i === $last) {
                continue;
            }
            $mid = $y + intdiv($bh, 2);
            if ($col < $perRow - 1) {
                // Within a row: right on even rows, left on odd rows.
                if ($row % 2 === 0) {
                    $this->arrow($im, $x + $bw + 6, $mid, $x + $bw + $gap - 6, $mid);
                } else {
                    $this->arrow($im, $x - 6, $mid, $x - $gap + 6, $mid);
                }
            } else {
                $this->arrow($im, $x + intdiv($bw, 2), $y + $bh + 6, $x + intdiv($bw, 2), $y + $bh + 64);
            }
        }
    }

    private function hub($im, string $font, string $center, array $nodes): void
    {
        $cx = self::W / 2;
        $cy = 420;
        $n = count($nodes);
        $positions = [];
        foreach (array_values($nodes) as $i => $label) {
            $angle = (-90 + (360 / $n) * $i) * M_PI / 180;
            $positions[] = [(int) ($cx + cos($angle) * 430 * 0.95), (int) ($cy + sin($angle) * 235), trim($label)];
        }
        foreach ($positions as [$x, $y]) {
            imagesetthickness($im, 3);
            imageline($im, (int) $cx, $cy, $x, $y, $this->c($im, self::MUTED));
        }
        imagesetthickness($im, 1);
        foreach ($positions as [$x, $y, $label]) {
            $this->box($im, $font, $x - 150, $y - 55, 300, 110, $label, false);
        }
        $this->box($im, $font, (int) $cx - 170, $cy - 70, 340, 140, trim($center), true);
    }

    private function compare($im, string $font, array $spec): void
    {
        foreach (['left' => 60, 'right' => 660] as $side => $x) {
            $col = $spec[$side];
            $w = 560;
            $items = array_map(fn ($i) => trim((string) $i), (array) $col['items']);
            $size = count($items) > 3 ? 20 : 24;
            $lineH = (int) ($size * 1.4);

            imagefilledrectangle($im, $x, 160, $x + $w, 240, $this->c($im, self::INDIGO));
            $this->text($im, $font, 28, self::WHITE, $x + 24, 212, trim((string) $col['heading']), $w - 48, true);

            $y = 262;
            foreach ($items as $item) {
                // Each box is as tall as its own text, so a two-line item never spills out of it.
                $lines = $this->wrap($font, $size, $item, $w - 40);
                $h = max(64, count($lines) * $lineH + 28);
                imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $this->c($im, self::INDIGO_SOFT));
                imagerectangle($im, $x, $y, $x + $w, $y + $h, $this->c($im, self::SLATE_BORDER));
                $top = $y + intdiv($h - count($lines) * $lineH, 2) + (int) ($size * 1.05);
                foreach ($lines as $n => $line) {
                    imagettftext($im, $size, 0, $x + 20, $top + $n * $lineH, $this->c($im, self::INK), $font, $line);
                }
                $y += $h + 14;
            }
        }
    }

    private function box($im, string $font, int $x, int $y, int $w, int $h, string $label, bool $accent): void
    {
        imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $this->c($im, $accent ? self::INDIGO : self::INDIGO_SOFT));
        imagerectangle($im, $x, $y, $x + $w, $y + $h, $this->c($im, $accent ? self::INDIGO : self::SLATE_BORDER));
        $ink = $accent ? self::WHITE : self::INK;
        $lines = $this->wrap($font, 26, $label, $w - 36);
        $lineH = 38;
        $startY = $y + intdiv($h - count($lines) * $lineH, 2) + 28;
        foreach ($lines as $i => $line) {
            $box = imagettfbbox(26, 0, $font, $line);
            $tx = $x + intdiv($w - ($box[2] - $box[0]), 2);
            imagettftext($im, 26, 0, $tx, $startY + $i * $lineH, $this->c($im, $ink), $font, $line);
        }
    }

    private function arrow($im, int $x1, int $y1, int $x2, int $y2): void
    {
        $col = $this->c($im, self::MUTED);
        imagesetthickness($im, 4);
        imageline($im, $x1, $y1, $x2, $y2, $col);
        imagesetthickness($im, 1);
        $a = atan2($y2 - $y1, $x2 - $x1);
        $head = [
            $x2, $y2,
            (int) ($x2 - 16 * cos($a - 0.45)), (int) ($y2 - 16 * sin($a - 0.45)),
            (int) ($x2 - 16 * cos($a + 0.45)), (int) ($y2 - 16 * sin($a + 0.45)),
        ];
        imagefilledpolygon($im, $head, $col);
    }

    private function text($im, string $font, int $size, array $rgb, int $x, int $y, string $text, int $maxWidth, bool $bold): void
    {
        foreach ($this->wrap($font, $size, $text, $maxWidth) as $i => $line) {
            imagettftext($im, $size, 0, $x, $y + $i * (int) ($size * 1.35), $this->c($im, $rgb), $font, $line);
        }
    }

    /** @return array<int,string> */
    private function wrap(string $font, int $size, string $text, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            $b = imagettfbbox($size, 0, $font, $try);
            if (($b[2] - $b[0]) > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function c($im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function font(): string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (!is_file($path)) {
            throw new \RuntimeException('Diagram font not found: ' . $path);
        }

        return $path;
    }
}
