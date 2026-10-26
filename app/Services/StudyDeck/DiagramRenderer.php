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
 * Look: the lesson's own palette (blue, teal, violet, cyan, amber, rose, the same families the student player uses per
 * topic), each box a pale tint with a band of its solid colour, soft shadows, rounded corners and a pale wash behind.
 * It is drawn at twice the size and scaled down so edges are smooth. The GEOMETRY (where each box and heading sits) is
 * unchanged by the look, because `anchors()` reports it and the player lays its hotspots over the picture at those points.
 * Colours are written once here because GD needs numbers; they are the player's tokens, not new colours.
 */
class DiagramRenderer
{
    public const LAYOUTS = ['flow', 'compare', 'hub'];

    /** The size of a hub node's box; `anchors()` reports the same box the drawing uses. */
    private const HUB_W = 320;

    private const HUB_H = 130;

    private const W = 1280;
    private const H = 720;

    /** Drawing scale: the picture is drawn this many times larger and scaled down. */
    private const SS = 2;

    /** Height of the coloured band along the top of a pale box. */
    private const BAND = 14;

    // EduERP tokens: --action-primary, --surface-subtle, --border-subtle, --content-primary, --content-secondary.
    private const INDIGO = [79, 70, 229];
    private const INDIGO_SOFT = [238, 242, 255];
    private const SLATE_BORDER = [203, 213, 225];
    private const INK = [15, 23, 42];
    private const MUTED = [71, 85, 105];
    private const WHITE = [255, 255, 255];
    private const SLATE_DISC = [51, 65, 85];
    private const SHADOW = [15, 23, 42];

    /** Solid colour and pale tint per step. Solids carry white text (4.5:1); tints carry the dark ink. */
    private const TONES = [
        ['solid' => [29, 78, 216], 'tint' => [219, 234, 254]],
        ['solid' => [15, 118, 110], 'tint' => [204, 251, 241]],
        ['solid' => [124, 58, 237], 'tint' => [237, 233, 254]],
        ['solid' => [14, 116, 144], 'tint' => [207, 250, 254]],
        ['solid' => [180, 83, 9], 'tint' => [254, 243, 199]],
        ['solid' => [190, 24, 93], 'tint' => [252, 231, 243]],
    ];

    /** Steps that are not the one being looked at are drawn in these quiet greys, so the step in focus stands out. */
    private const QUIET = ['solid' => [148, 163, 184], 'tint' => [241, 245, 249]];

    /** The ring drawn round the step in focus. */
    private const RING = [245, 158, 11];

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
        // `focus` (flow only): the zero-based step to emphasise, for a sequence shown once for each of its steps.
        if (array_key_exists('focus', $spec) && $spec['focus'] !== null) {
            $count = count(array_filter(array_map(fn ($x) => trim((string) (is_array($x) ? ($x['text'] ?? '') : $x)), (array) ($spec['nodes'] ?? []))));
            if ($layout !== 'flow' || !is_int($spec['focus']) || $spec['focus'] < 0 || $spec['focus'] >= $count) {
                $out[] = 'focus must be the number of one step of a flow diagram';
            }
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
            'flow' => "Diagram: $title. Steps in order: " . implode(', then ', $nodes) . '.'
                . (isset($spec['focus'], $nodes[$spec['focus']]) ? ' The step in focus is ' . $nodes[$spec['focus']] . '.' : ''),
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
        $bold = $this->boldFont();

        // Drawn at twice the size and scaled down, so the rounded corners and the text have smooth edges.
        $im = imagecreatetruecolor(self::W * self::SS, self::H * self::SS);
        imagealphablending($im, true);
        imageantialias($im, true);
        $this->backdrop($im);

        [$titleSize] = $this->fit($bold, (string) $spec['title'], self::W - 120, 44, [34, 30, 26, 22], 1.2);
        $this->text($im, $bold, $titleSize, self::INK, 60, 70, (string) $spec['title'], self::W - 120);
        // A short bar in the three colours that carry through the diagram.
        foreach ([self::TONES[0], self::TONES[1], self::TONES[2]] as $k => $tone) {
            $this->rect($im, 60 + $k * 44, 96, 44, 6, $tone['solid']);
        }

        $nodes = fn () => array_map(fn ($n) => is_array($n) ? $n['text'] : $n, (array) $spec['nodes']);
        match ($spec['layout']) {
            'flow' => $this->flow($im, $bold, $nodes(), isset($spec['focus']) ? (int) $spec['focus'] : null),
            'hub' => $this->hub($im, $bold, (string) $spec['center'], $nodes()),
            'compare' => $this->compare($im, $font, $bold, $spec),
        };

        $out = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($out, $im, 0, 0, 0, 0, self::W, self::H, self::W * self::SS, self::H * self::SS);
        imagedestroy($im);
        ob_start();
        imagepng($out);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);

        return ['bytes' => $bytes, 'mime' => 'image/png', 'width' => self::W, 'height' => self::H];
    }

    /**
     * A plainly labelled stand-in picture for a scene no picture could be found for: the scene's topic and the ideas it teaches, and a visible
     * stamp saying it is a placeholder. It is a real PNG like any drawn diagram. Not a layout a model can ask for (it is not in LAYOUTS): only
     * the picture step calls it, as the last rung of the fallback ladder.
     *
     * @param array<int,string> $ideas up to three concept names
     * @return array{bytes:string,mime:string,width:int,height:int,texts:array<int,string>}
     */
    public function placeholder(string $topic, array $ideas, string $stamp): array
    {
        $topic = trim((string) preg_replace('/\s+/u', ' ', $topic)) ?: 'Science';
        $ideas = array_slice(array_values(array_filter(array_map(fn ($t) => trim((string) preg_replace('/\s+/u', ' ', (string) $t)), $ideas))), 0, 3);
        $font = $this->font();
        $bold = $this->boldFont();

        $im = imagecreatetruecolor(self::W * self::SS, self::H * self::SS);
        imagealphablending($im, true);
        imageantialias($im, true);
        $this->backdrop($im);

        $this->text($im, $bold, 22, self::MUTED, 60, 70, 'SCENE TOPIC', self::W - 120);
        [$titleSize, $titleLines] = $this->fit($bold, $topic, self::W - 120, 190, [54, 46, 40, 34, 28], 1.4);
        foreach ($titleLines as $i => $line) {
            $this->ttf($im, $bold, $titleSize, self::INK, 60, 150 + (int) round($titleSize * 1.4) * $i, $line);
        }
        foreach ([self::TONES[0], self::TONES[1], self::TONES[2]] as $k => $tone) {
            $this->rect($im, 60 + $k * 44, 160 + (int) round($titleSize * 1.4) * count($titleLines), 44, 6, $tone['solid']);
        }

        if ($ideas !== []) {
            $count = count($ideas);
            $gap = 28;
            $w = intdiv(self::W - 120 - $gap * ($count - 1), $count);
            foreach ($ideas as $i => $idea) {
                $this->box($im, $bold, 60 + $i * ($w + $gap), 400, $w, 130, $idea, false, self::TONES[$i % 3]);
            }
        }

        // The stamp: a bar across the foot of the picture that says what this is.
        $this->rrect($im, 60, 590, self::W - 120, 80, 18, self::INK);
        $this->circle($im, 112, 630, 18, self::RING, 0);
        [$stampSize, $stampLines] = $this->fit($bold, $stamp, self::W - 270, 64, [26, 24, 22, 20, 18], 1.5);
        $startY = 630 + (int) round($stampSize * 0.36) - (int) round((count($stampLines) - 1) * $stampSize * 1.5 / 2);
        foreach ($stampLines as $i => $line) {
            $this->ttf($im, $bold, $stampSize, self::WHITE, 150, $startY + (int) round($stampSize * 1.5) * $i, $line);
        }

        $out = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($out, $im, 0, 0, 0, 0, self::W, self::H, self::W * self::SS, self::H * self::SS);
        imagedestroy($im);
        ob_start();
        imagepng($out);
        $bytes = (string) ob_get_clean();
        imagedestroy($out);

        return ['bytes' => $bytes, 'mime' => 'image/png', 'width' => self::W, 'height' => self::H, 'texts' => array_merge(['Scene topic', $topic], $ideas, [$stamp])];
    }

    /** A pale vertical wash and a few faint cells in the top-right corner: calm, and never behind a label. */
    private function backdrop($im): void
    {
        $top = [248, 250, 252];
        $bottom = [238, 242, 255];
        $h = self::H * self::SS;
        for ($y = 0; $y < $h; $y += 2) {
            $t = $y / $h;
            $col = $this->c($im, [(int) ($top[0] + ($bottom[0] - $top[0]) * $t), (int) ($top[1] + ($bottom[1] - $top[1]) * $t), (int) ($top[2] + ($bottom[2] - $top[2]) * $t)]);
            imagefilledrectangle($im, 0, $y, self::W * self::SS, $y + 1, $col);
        }
        $this->circle($im, 1236, 38, 118, self::TONES[0]['solid'], 108);
        $this->circle($im, 1236, 38, 74, self::TONES[0]['solid'], 112);
        $this->circle($im, 1120, 14, 40, self::TONES[1]['solid'], 110);
        $this->circle($im, 40, 700, 96, self::TONES[1]['solid'], 112);
    }

    private function flow($im, string $bold, array $nodes, ?int $focus = null): void
    {
        $nodes = array_values(array_map('trim', $nodes));
        ['perRow' => $perRow, 'bw' => $bw, 'bh' => $bh, 'gap' => $gap, 'place' => $place] = self::flowGeometry(count($nodes));
        $last = count($nodes) - 1;

        // Arrows first, so a box is never crossed by one.
        foreach ($nodes as $i => $label) {
            [$x, $y, $row, $col] = $place($i);
            if ($i === $last) {
                continue;
            }
            $mid = $y + intdiv($bh, 2);
            $tone = $focus === null ? self::TONES[$i % count(self::TONES)] : self::QUIET;
            if ($col < $perRow - 1) {
                // Within a row: right on even rows, left on odd rows.
                if ($row % 2 === 0) {
                    $this->arrow($im, $x + $bw + 6, $mid, $x + $bw + $gap - 6, $mid, $tone['solid']);
                } else {
                    $this->arrow($im, $x - 6, $mid, $x - $gap + 6, $mid, $tone['solid']);
                }
            } else {
                $this->arrow($im, $x + intdiv($bw, 2), $y + $bh + 6, $x + intdiv($bw, 2), $y + $bh + 64, $tone['solid']);
            }
        }
        foreach ($nodes as $i => $label) {
            [$x, $y] = $place($i);
            if ($focus === null) {
                $this->box($im, $bold, $x, $y, $bw, $bh, $label, $i === 0 || $i === $last, self::TONES[$i % count(self::TONES)]);
                continue;
            }
            // One step in focus: a ring and its own colour; the others recede.
            if ($i === $focus) {
                $this->rrect($im, $x - 9, $y - 9, $bw + 18, $bh + 18, 30, self::RING);
            }
            $this->box($im, $bold, $x, $y, $bw, $bh, $label, $i === $focus, $i === $focus ? self::TONES[$i % count(self::TONES)] : self::QUIET);
        }
    }

    private function hub($im, string $bold, string $center, array $nodes): void
    {
        $cx = (int) (self::W / 2);
        $cy = 420;
        $positions = [];
        foreach (self::hubPositions(count($nodes)) as $i => [$px, $py]) {
            $positions[] = [$px, $py, trim(array_values($nodes)[$i])];
        }
        foreach ($positions as $i => [$x, $y]) {
            $this->line($im, $cx, $cy, $x, $y, 7, self::TONES[$i % count(self::TONES)]['solid']);
        }
        foreach ($positions as $i => [$x, $y, $label]) {
            $this->box($im, $bold, $x - intdiv(self::HUB_W, 2), $y - intdiv(self::HUB_H, 2), self::HUB_W, self::HUB_H, $label, false, self::TONES[$i % count(self::TONES)]);
        }
        // The centre: a solid pill in the brand indigo.
        $this->box($im, $bold, $cx - 170, $cy - 70, 340, 140, trim($center), true, ['solid' => self::INDIGO, 'tint' => self::INDIGO_SOFT], 70);
    }

    private function compare($im, string $font, string $bold, array $spec): void
    {
        // Both headings share one size (the smaller of what each needs), so the two columns read as a pair.
        $headSize = min(array_map(
            fn ($side) => $this->fit($bold, trim((string) $spec[$side]['heading']), 560 - 48, 62, [28, 24, 22, 20], 1.25)[0],
            ['left', 'right']
        ));

        foreach (['left' => [60, self::TONES[0]], 'right' => [660, self::TONES[1]]] as $side => [$x, $tone]) {
            $col = $spec[$side];
            $w = 560;
            $items = array_map(fn ($i) => trim((string) $i), (array) $col['items']);
            $size = count($items) > 3 ? 20 : 24;
            $lineH = (int) ($size * 1.4);

            $this->shadow($im, $x, 160, $w, 80, 18);
            $this->rrect($im, $x, 160, $w, 80, 18, $tone['solid']);
            $hl = $this->wrap($bold, $headSize, trim((string) $col['heading']), $w - 48);
            $hh = (int) round($headSize * 1.25);
            $top = 200 - intdiv(count($hl) * $hh, 2);
            foreach ($hl as $n => $line) {
                $this->ttf($im, $bold, $headSize, self::WHITE, $x + 24, $top + ($n + 1) * $hh - (int) round($hh * 0.22), $line);
            }

            $y = 262;
            foreach ($items as $item) {
                // Each box is as tall as its own text, so a two-line item never spills out of it.
                $lines = $this->wrap($font, $size, $item, $w - 52);
                $h = max(64, count($lines) * $lineH + 28);
                $this->shadow($im, $x, $y, $w, $h, 14);
                $this->rrect($im, $x, $y, $w, $h, 14, $tone['solid']);
                $this->rrect($im, $x + 14, $y + 3, $w - 17, $h - 6, 11, $tone['tint']);
                $top = $y + intdiv($h - count($lines) * $lineH, 2) + (int) ($size * 1.05);
                foreach ($lines as $n => $line) {
                    $this->ttf($im, $font, $size, self::INK, $x + 32, $top + $n * $lineH, $line);
                }
                $y += $h + 14;
            }
        }

        // The two sides are set against each other: a small disc where the columns meet.
        $this->circle($im, 640, 200, 30, self::SLATE_DISC, 0);
        $vs = 'VS';
        $b = imagettfbbox(18 * self::SS, 0, $bold, $vs);
        $this->ttf($im, $bold, 18, self::WHITE, 640 - (int) round(($b[2] - $b[0]) / self::SS / 2), 207, $vs);
    }

    private function box($im, string $bold, int $x, int $y, int $w, int $h, string $label, bool $accent, array $tone, int $radius = 22): void
    {
        $this->shadow($im, $x, $y, $w, $h, $radius);
        if ($accent) {
            $this->rrect($im, $x, $y, $w, $h, $radius, $tone['solid']);
        } else {
            // The outline and a band of the step's colour along the top are one shape; the tint sits inside it, below the band.
            $this->rrect($im, $x, $y, $w, $h, $radius, $tone['solid']);
            $this->rrect($im, $x + 3, $y + self::BAND, $w - 6, $h - self::BAND - 3, max(4, $radius - 3), $tone['tint']);
        }
        $ink = $accent ? self::WHITE : self::INK;
        // The text sits in what is left under the band.
        $band = $accent ? 0 : self::BAND - 3;
        [$size, $lines] = $this->fit($bold, $label, $w - 36, $h - 20 - $band, [26, 24, 22, 20, 18], 1.45);
        $lineH = (int) round($size * 1.45);
        $startY = $y + $band + intdiv($h - $band - count($lines) * $lineH, 2) + (int) round($size * 1.05);
        foreach ($lines as $i => $line) {
            $box = imagettfbbox($size * self::SS, 0, $bold, $line);
            $tx = $x + intdiv($w - (int) round(($box[2] - $box[0]) / self::SS), 2);
            $this->ttf($im, $bold, $size, $ink, $tx, $startY + $i * $lineH, $line);
        }
    }

    private function arrow($im, int $x1, int $y1, int $x2, int $y2, array $rgb): void
    {
        $this->line($im, $x1, $y1, $x2, $y2, 6, $rgb);
        $a = atan2($y2 - $y1, $x2 - $x1);
        $head = [
            $x2 + (int) (6 * cos($a)), $y2 + (int) (6 * sin($a)),
            (int) ($x2 - 22 * cos($a - 0.5)), (int) ($y2 - 22 * sin($a - 0.5)),
            (int) ($x2 - 22 * cos($a + 0.5)), (int) ($y2 - 22 * sin($a + 0.5)),
        ];
        $this->poly($im, $head, $rgb);
    }

    // -- GD drawing in the picture's own units; every call scales to the 2x canvas --------------------------------

    private function rect($im, int $x, int $y, int $w, int $h, array $rgb, int $alpha = 0): void
    {
        $s = self::SS;
        imagefilledrectangle($im, $x * $s, $y * $s, ($x + $w) * $s - 1, ($y + $h) * $s - 1, $this->c($im, $rgb, $alpha));
    }

    private function rrect($im, int $x, int $y, int $w, int $h, int $r, array $rgb, int $alpha = 0): void
    {
        $s = self::SS;
        $r = max(0, min($r, intdiv(min($w, $h), 2)));
        $c = $this->c($im, $rgb, $alpha);
        [$x1, $y1, $x2, $y2, $rr] = [$x * $s, $y * $s, ($x + $w) * $s - 1, ($y + $h) * $s - 1, $r * $s];
        imagefilledrectangle($im, $x1 + $rr, $y1, $x2 - $rr, $y2, $c);
        imagefilledrectangle($im, $x1, $y1 + $rr, $x2, $y2 - $rr, $c);
        foreach ([[$x1 + $rr, $y1 + $rr], [$x2 - $rr, $y1 + $rr], [$x1 + $rr, $y2 - $rr], [$x2 - $rr, $y2 - $rr]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $rr * 2, $rr * 2, $c);
        }
    }

    /**
     * A soft shadow: three opaque, growing layers just below the shape, each a little darker than the wash behind.
     * (Translucent layers double up where their rounded corners overlap and leave dark blobs.)
     */
    private function shadow($im, int $x, int $y, int $w, int $h, int $r): void
    {
        foreach ([[7, [229, 233, 246]], [4, [220, 225, 241]], [2, [210, 216, 235]]] as [$grow, $rgb]) {
            $this->rrect($im, $x - $grow, $y + 6 - $grow + 2, $w + 2 * $grow, $h + 2 * $grow, $r + $grow, $rgb);
        }
    }

    private function circle($im, int $cx, int $cy, int $r, array $rgb, int $alpha): void
    {
        $s = self::SS;
        imagefilledellipse($im, $cx * $s, $cy * $s, $r * 2 * $s, $r * 2 * $s, $this->c($im, $rgb, $alpha));
    }

    private function line($im, int $x1, int $y1, int $x2, int $y2, int $thickness, array $rgb): void
    {
        $a = atan2($y2 - $y1, $x2 - $x1);
        $dx = -sin($a) * $thickness / 2;
        $dy = cos($a) * $thickness / 2;
        $s = self::SS;
        imagefilledpolygon($im, [
            (int) round(($x1 + $dx) * $s), (int) round(($y1 + $dy) * $s),
            (int) round(($x2 + $dx) * $s), (int) round(($y2 + $dy) * $s),
            (int) round(($x2 - $dx) * $s), (int) round(($y2 - $dy) * $s),
            (int) round(($x1 - $dx) * $s), (int) round(($y1 - $dy) * $s),
        ], $this->c($im, $rgb));
    }

    /** @param array<int,int> $points x, y pairs */
    private function poly($im, array $points, array $rgb): void
    {
        imagefilledpolygon($im, array_map(fn ($v) => $v * self::SS, $points), $this->c($im, $rgb));
    }

    private function ttf($im, string $font, int $size, array $rgb, int $x, int $y, string $text): void
    {
        imagettftext($im, $size * self::SS, 0, $x * self::SS, $y * self::SS, $this->c($im, $rgb), $font, $text);
    }

    private function text($im, string $font, int $size, array $rgb, int $x, int $y, string $text, int $maxWidth): void
    {
        foreach ($this->wrap($font, $size, $text, $maxWidth) as $i => $line) {
            $this->ttf($im, $font, $size, $rgb, $x, $y + $i * (int) ($size * 1.35), $line);
        }
    }

    /**
     * Where each flow box sits. Rows snake: the second row runs right to left, so the arrow that
     * leaves the end of one row lands directly on the first box of the next.
     *
     * @return array{perRow:int,bw:int,bh:int,gap:int,place:callable}
     */
    private static function flowGeometry(int $count): array
    {
        $perRow = $count <= 4 ? $count : (int) ceil($count / 2);
        $rows = (int) ceil($count / $perRow);
        $gap = 54;
        $bw = (int) ((self::W - 120 - $gap * ($perRow - 1)) / $perRow);
        $bh = $rows === 1 ? 190 : 150;
        $top = $rows === 1 ? 280 : 200;

        $place = function (int $i) use ($perRow, $bw, $gap, $bh, $top): array {
            $row = intdiv($i, $perRow);
            $col = $i % $perRow;
            $slot = $row % 2 === 0 ? $col : $perRow - 1 - $col;

            return [60 + $slot * ($bw + $gap), $top + $row * ($bh + 70), $row, $col];
        };

        return ['perRow' => $perRow, 'bw' => $bw, 'bh' => $bh, 'gap' => $gap, 'place' => $place];
    }

    /** Hub node centres, shared by the drawing and by `anchors()`. @return array<int,array{0:int,1:int}> */
    private static function hubPositions(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = (-90 + (360 / $count) * $i) * M_PI / 180;
            $out[] = [(int) (self::W / 2 + cos($angle) * 410), (int) (420 + sin($angle) * 212)];
        }

        return $out;
    }

    /**
     * The clickable places on a drawn diagram: each label with its box (centre and size) as a share
     * of the picture (0-100). The student player lays hotspots over the image at these points, so
     * the picture and its hotspots can never disagree about where a label is.
     *
     * Flow and hub: every node. Compare: the two headings.
     *
     * @return array<int,array{label:string,x:float,y:float,w:float,h:float}>
     */
    public static function anchors(array $spec): array
    {
        $at = fn (string $label, float $x, float $y, float $w, float $h) => [
            'label' => $label,
            'x' => round($x / self::W * 100, 1), 'y' => round($y / self::H * 100, 1),
            'w' => round($w / self::W * 100, 1), 'h' => round($h / self::H * 100, 1),
        ];
        $text = static fn ($n) => trim((string) (is_array($n) ? ($n['text'] ?? '') : $n));
        $layout = $spec['layout'] ?? '';

        if ($layout === 'compare') {
            return [
                $at(trim((string) $spec['left']['heading']), 60 + 280, 200, 560, 80),
                $at(trim((string) $spec['right']['heading']), 660 + 280, 200, 560, 80),
            ];
        }

        $nodes = array_values(array_map($text, (array) ($spec['nodes'] ?? [])));
        if ($layout === 'hub') {
            $points = self::hubPositions(count($nodes));

            return array_map(fn ($i) => $at($nodes[$i], $points[$i][0], $points[$i][1], self::HUB_W, self::HUB_H), array_keys($nodes));
        }

        $g = self::flowGeometry(count($nodes));

        return array_map(function ($i) use ($nodes, $g, $at) {
            [$x, $y] = ($g['place'])($i);

            return $at($nodes[$i], $x + $g['bw'] / 2, $y + $g['bh'] / 2, $g['bw'], $g['bh']);
        }, array_keys($nodes));
    }

    /**
     * The largest of `$sizes` at which `$text` wraps into `$maxHeight`; the smallest if none does.
     * A label always stays inside its box: it gets smaller before it spills.
     *
     * @param array<int,int> $sizes largest first
     * @return array{0:int,1:array<int,string>} [size, wrapped lines]
     */
    public function fit(string $font, string $text, int $maxWidth, int $maxHeight, array $sizes, float $lineHeight = 1.4): array
    {
        foreach ($sizes as $size) {
            $lines = $this->wrap($font, $size, $text, $maxWidth);
            // Both ways: few enough lines, and no line (a long unbreakable word) wider than the box.
            if (count($lines) * $size * $lineHeight <= $maxHeight && $this->widest($font, $size, $lines) <= $maxWidth) {
                return [$size, $lines];
            }
        }
        $size = end($sizes);

        return [$size, $this->wrap($font, $size, $text, $maxWidth)];
    }

    /** @param array<int,string> $lines */
    private function widest(string $font, int $size, array $lines): int
    {
        $widest = 0;
        foreach ($lines as $line) {
            $b = imagettfbbox($size, 0, $font, $line);
            $widest = max($widest, $b[2] - $b[0]);
        }

        return $widest;
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

    /** @param int $alpha 0 (opaque) to 127 (invisible) */
    private function c($im, array $rgb, int $alpha = 0): int
    {
        return $alpha > 0 ? imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha) : imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    public function font(): string
    {
        return $this->fontFile('DejaVuSans.ttf');
    }

    public function boldFont(): string
    {
        return $this->fontFile('DejaVuSans-Bold.ttf');
    }

    private function fontFile(string $name): string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/' . $name);
        if (!is_file($path)) {
            throw new RuntimeException('Diagram font not found: ' . $path);
        }

        return $path;
    }
}
