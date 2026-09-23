<?php
/** Shared SVG drawing helpers for the chapter 8677 question-bank figures. */
/* ------------------------------------------------------------ svg helpers */

const INK    = '#1e293b'; // axis / text
const MUTED  = '#94a3b8'; // secondary rule
const ACCENT = '#2563eb'; // the thing being shown
const WARM   = '#ea580c'; // the second quantity / the error
const GOOD   = '#15803d'; // the result
const PAPER  = '#ffffff';

function svg(int $w, int $h, string $body): string
{
    $paper = PAPER;
    $f = 'font-family="ui-sans-serif,system-ui,Segoe UI,Arial,sans-serif"';
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" role="img" {$f}>
<rect width="{$w}" height="{$h}" fill="{$paper}"/>
{$body}
</svg>
SVG;
}

function txt($x, $y, string $s, int $size = 13, string $fill = INK, string $anchor = 'middle', string $weight = '400'): string
{
    $s = htmlspecialchars($s, ENT_XML1);
    return "<text x=\"{$x}\" y=\"{$y}\" font-size=\"{$size}\" fill=\"{$fill}\" text-anchor=\"{$anchor}\" font-weight=\"{$weight}\">{$s}</text>";
}

/** Horizontal number line from $min to $max with optional arrow jumps. */
function numberLine(int $min, int $max, array $jumps = [], array $opts = []): array
{
    $step   = $opts['step'] ?? 1;
    $pad    = 42;
    $w      = $opts['w'] ?? 620;
    $topPad = 46 + 26 * max(0, count($jumps) - 1);
    $h      = $topPad + 74;
    $y      = $topPad + 20;
    $span   = $w - 2 * $pad;
    $n      = ($max - $min) / $step;
    $px     = fn($v) => $pad + ($v - $min) / ($max - $min) * $span;

    $b = "<line x1=\"{$pad}\" y1=\"{$y}\" x2=\"" . ($w - $pad) . "\" y2=\"{$y}\" stroke=\"" . INK . "\" stroke-width=\"1.5\"/>";
    // arrow heads both ways
    $b .= "<path d=\"M" . ($pad - 9) . " {$y} l9 -5 v10 z\" fill=\"" . INK . "\"/>";
    $b .= "<path d=\"M" . ($w - $pad + 9) . " {$y} l-9 -5 v10 z\" fill=\"" . INK . "\"/>";

    for ($v = $min; $v <= $max; $v += $step) {
        $x = $px($v);
        $isZero = ($v === 0);
        $col = $isZero ? ACCENT : INK;
        $len = $isZero ? 9 : 6;
        $b .= "<line x1=\"{$x}\" y1=\"" . ($y - $len) . "\" x2=\"{$x}\" y2=\"" . ($y + $len) . "\" stroke=\"{$col}\" stroke-width=\"" . ($isZero ? 2.2 : 1.2) . "\"/>";
        if ($n <= 26) {
            $b .= txt($x, $y + 26, (string) $v, 12, $isZero ? ACCENT : MUTED, 'middle', $isZero ? '700' : '400');
        }
    }

    // jumps drawn as arcs above the line
    $level = 0;
    foreach ($jumps as $j) {
        [$from, $to, $label, $col] = array_pad((array) $j, 4, null);
        $col = $col ?: ACCENT;
        $x1 = $px($from); $x2 = $px($to);
        $lift = 22 + 26 * $level;
        $mid = ($x1 + $x2) / 2;
        $cy  = $y - $lift;
        $b .= "<path d=\"M{$x1} " . ($y - 8) . " Q{$mid} " . ($cy - 12) . " {$x2} " . ($y - 8) . "\" fill=\"none\" stroke=\"{$col}\" stroke-width=\"2\"/>";
        $dir = $x2 >= $x1 ? 1 : -1;
        $ax = $x2; $ay = $y - 8;
        $b .= "<path d=\"M{$ax} {$ay} l" . (-6 * $dir) . " -5 v10 z\" fill=\"{$col}\"/>";
        if ($label) { $b .= txt($mid, $cy - 16, $label, 12, $col, 'middle', '600'); }
        $level++;
    }

    return [$w, $h, $b];
}

/** n by n array of unit squares, for square numbers. */
function squareArray(int $n, string $caption): array
{
    $cell = $n <= 5 ? 26 : 18;
    $gap  = 3;
    $side = $n * $cell + ($n - 1) * $gap;
    $w = max(240, $side + 48); $h = $side + 66;
    $x0 = ($w - $side) / 2; $y0 = 16;
    $b = '';
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            $x = $x0 + $c * ($cell + $gap); $y = $y0 + $r * ($cell + $gap);
            $b .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$cell}\" height=\"{$cell}\" rx=\"3\" fill=\"#dbeafe\" stroke=\"" . ACCENT . "\" stroke-width=\"1.2\"/>";
        }
    }
    $b .= txt($w / 2, $y0 + $side + 30, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $b];
}

/** Stacked layers representing a cube of side n. */
function cubeModel(int $n, string $caption): array
{
    $cell = 16; $gap = 2; $skew = 9;
    $side = $n * $cell + ($n - 1) * $gap;
    $w = max(260, $side + $skew * $n + 60); $h = $side + $skew * $n + 70;
    $x0 = 30; $y0 = $skew * $n + 14;
    $b = '';
    for ($layer = $n - 1; $layer >= 0; $layer--) {
        $ox = $layer * $skew; $oy = -$layer * $skew;
        $shade = ['#bfdbfe', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb'][min($layer, 4)];
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                $x = $x0 + $ox + $c * ($cell + $gap);
                $y = $y0 + $oy + $r * ($cell + $gap);
                $b .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$cell}\" height=\"{$cell}\" rx=\"2\" fill=\"{$shade}\" stroke=\"" . INK . "\" stroke-width=\"0.7\" opacity=\"0.95\"/>";
            }
        }
    }
    $b .= txt($w / 2, $h - 18, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $b];
}

/** Two overlapping sets of factors. */
function factorVenn(int $a, int $b, array $fa, array $fb, string $caption): array
{
    $common = array_values(array_intersect($fa, $fb));
    $onlyA  = array_values(array_diff($fa, $common));
    $onlyB  = array_values(array_diff($fb, $common));
    $w = 560; $h = 250;
    $cy = 115; $r = 88;
    $cxA = 195; $cxB = 365;
    $body  = "<circle cx=\"{$cxA}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"#dbeafe\" fill-opacity=\".65\" stroke=\"" . ACCENT . "\" stroke-width=\"1.6\"/>";
    $body .= "<circle cx=\"{$cxB}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"#ffedd5\" fill-opacity=\".65\" stroke=\"" . WARM . "\" stroke-width=\"1.6\"/>";
    $body .= txt($cxA - 46, 34, "factors of {$a}", 13, ACCENT, 'middle', '700');
    $body .= txt($cxB + 46, 34, "factors of {$b}", 13, WARM, 'middle', '700');
    $body .= txt($cxA - 42, $cy + 5, implode('  ', $onlyA) ?: '—', 15, INK);
    $body .= txt($cxB + 42, $cy + 5, implode('  ', $onlyB) ?: '—', 15, INK);
    $body .= txt(280, $cy - 6, implode('  ', $common), 15, GOOD, 'middle', '700');
    $body .= txt(280, $cy + 16, 'common', 11, GOOD);
    $body .= txt($w / 2, $h - 16, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $body];
}

/** Two rows of multiples with the shared values ringed. */
function multiplesTrack(int $a, int $b, int $limit, string $caption): array
{
    $ma = []; for ($k = $a; $k <= $limit; $k += $a) { $ma[] = $k; }
    $mb = []; for ($k = $b; $k <= $limit; $k += $b) { $mb[] = $k; }
    $common = array_values(array_intersect($ma, $mb));
    $w = 620; $h = 168; $pad = 78; $span = $w - $pad - 30;
    $px = fn($v) => $pad + $v / $limit * $span;
    $body = '';
    foreach ([[$ma, 54, ACCENT, "multiples of {$a}"], [$mb, 108, WARM, "multiples of {$b}"]] as [$set, $y, $col, $lab]) {
        $body .= "<line x1=\"{$pad}\" y1=\"{$y}\" x2=\"" . ($w - 30) . "\" y2=\"{$y}\" stroke=\"" . MUTED . "\" stroke-width=\"1\"/>";
        $body .= txt($pad - 10, $y + 4, $lab, 11, $col, 'end', '600');
        foreach ($set as $v) {
            $x = $px($v);
            $isC = in_array($v, $common, true);
            $body .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"" . ($isC ? 12 : 4.5) . "\" fill=\"" . ($isC ? '#dcfce7' : $col) . "\" stroke=\"" . ($isC ? GOOD : $col) . "\" stroke-width=\"" . ($isC ? 2 : 0) . "\"/>";
            $body .= txt($x, $y - ($isC ? 19 : 12), (string) $v, 10, $isC ? GOOD : MUTED, 'middle', $isC ? '700' : '400');
        }
    }
    $lcm = $common ? min($common) : null;
    if ($lcm !== null) {
        $x = $px($lcm);
        $body .= "<line x1=\"{$x}\" y1=\"38\" x2=\"{$x}\" y2=\"124\" stroke=\"" . GOOD . "\" stroke-width=\"1.4\" stroke-dasharray=\"4 3\"/>";
        $body .= txt($x, 30, "LCM = {$lcm}", 12, GOOD, 'middle', '700');
    }
    $body .= txt($w / 2, $h - 14, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $body];
}

/** Digit boxes with the digits a divisibility rule reads highlighted. */
function placeValue(string $digits, array $hot, string $caption, string $note = ''): array
{
    $n = strlen($digits);
    $bw = 40; $gap = 7;
    $tot = $n * $bw + ($n - 1) * $gap;
    $w = max(360, $tot + 60); $h = $note === '' ? 132 : 158;
    $x0 = ($w - $tot) / 2; $y0 = 34;
    $body = '';
    for ($i = 0; $i < $n; $i++) {
        $x = $x0 + $i * ($bw + $gap);
        $on = in_array($i, $hot, true);
        $body .= "<rect x=\"{$x}\" y=\"{$y0}\" width=\"{$bw}\" height=\"48\" rx=\"6\" fill=\"" . ($on ? '#dbeafe' : '#f8fafc') . "\" stroke=\"" . ($on ? ACCENT : MUTED) . "\" stroke-width=\"" . ($on ? 2.2 : 1) . "\"/>";
        $body .= txt($x + $bw / 2, $y0 + 32, $digits[$i], 20, $on ? ACCENT : INK, 'middle', $on ? '700' : '400');
    }
    $body .= txt($w / 2, 22, $caption, 13, INK, 'middle', '600');
    if ($note !== '') { $body .= txt($w / 2, $y0 + 78, $note, 13, GOOD, 'middle', '600'); }
    return [$w, $h, $body];
}

/** Factor-pair rainbow for a single number. */
function factorRainbow(int $num, array $factors, string $caption): array
{
    sort($factors);
    $n = count($factors);
    $w = 560; $h = 190; $pad = 50;
    $span = $w - 2 * $pad;
    $y = 132;
    $px = fn($i) => $pad + ($n === 1 ? $span / 2 : $i / ($n - 1) * $span);
    $body = '';
    for ($i = 0; $i < intdiv($n, 2); $i++) {
        $x1 = $px($i); $x2 = $px($n - 1 - $i);
        $lift = 26 + $i * 24;
        $mid = ($x1 + $x2) / 2;
        $body .= "<path d=\"M{$x1} " . ($y - 16) . " Q{$mid} " . ($y - 16 - $lift * 1.5) . " {$x2} " . ($y - 16) . "\" fill=\"none\" stroke=\"" . ACCENT . "\" stroke-width=\"1.6\" opacity=\"0.8\"/>";
        $body .= txt($mid, $y - 18 - $lift, $factors[$i] . ' x ' . $factors[$n - 1 - $i] . ' = ' . $num, 11, ACCENT, 'middle', '600');
    }
    foreach ($factors as $i => $f) {
        $x = $px($i);
        $body .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"14\" fill=\"#dbeafe\" stroke=\"" . ACCENT . "\" stroke-width=\"1.4\"/>";
        $body .= txt($x, $y + 5, (string) $f, 13, INK, 'middle', '600');
    }
    $body .= txt($w / 2, $h - 12, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $body];
}

/** Two-way inverse loop, e.g. 4 -> 16 -> 4. */
function inverseLoop(string $left, string $right, string $fwd, string $back, string $caption): array
{
    $w = 470; $h = 176;
    $body  = "<rect x=\"52\" y=\"56\" width=\"110\" height=\"56\" rx=\"10\" fill=\"#dbeafe\" stroke=\"" . ACCENT . "\" stroke-width=\"1.6\"/>";
    $body .= "<rect x=\"308\" y=\"56\" width=\"110\" height=\"56\" rx=\"10\" fill=\"#dcfce7\" stroke=\"" . GOOD . "\" stroke-width=\"1.6\"/>";
    $body .= txt(107, 92, $left, 22, INK, 'middle', '700');
    $body .= txt(363, 92, $right, 22, INK, 'middle', '700');
    $body .= "<path d=\"M168 72 Q235 36 302 72\" fill=\"none\" stroke=\"" . ACCENT . "\" stroke-width=\"2\"/>";
    $body .= "<path d=\"M302 72 l-9 -3 v9 z\" fill=\"" . ACCENT . "\"/>";
    $body .= txt(235, 32, $fwd, 12, ACCENT, 'middle', '600');
    $body .= "<path d=\"M302 100 Q235 140 168 100\" fill=\"none\" stroke=\"" . WARM . "\" stroke-width=\"2\"/>";
    $body .= "<path d=\"M168 100 l9 -3 v9 z\" fill=\"" . WARM . "\"/>";
    $body .= txt(235, 152, $back, 12, WARM, 'middle', '600');
    $body .= txt($w / 2, 22, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $body];
}

/** Equal-grouping picture for division / remainder. */
function grouping(int $total, int $per, string $caption): array
{
    $groups = intdiv($total, $per); $rem = $total % $per;
    $d = 15; $gap = 4; $gpad = 12;
    $gw = $per * $d + ($per - 1) * $gap + 2 * $gpad;
    $cols = min($groups + ($rem ? 1 : 0), 5);
    $rows = (int) ceil(($groups + ($rem ? 1 : 0)) / $cols);
    $w = max(340, $cols * ($gw + 12) + 24); $h = $rows * 58 + 66;
    $body = ''; $i = 0;
    for ($g = 0; $g < $groups; $g++, $i++) {
        $cx = 12 + ($i % $cols) * ($gw + 12); $cy = 34 + intdiv($i, $cols) * 58;
        $body .= "<rect x=\"{$cx}\" y=\"{$cy}\" width=\"{$gw}\" height=\"40\" rx=\"8\" fill=\"#eff6ff\" stroke=\"" . ACCENT . "\" stroke-width=\"1.3\"/>";
        for ($k = 0; $k < $per; $k++) {
            $x = $cx + $gpad + $k * ($d + $gap) + $d / 2;
            $body .= "<circle cx=\"{$x}\" cy=\"" . ($cy + 20) . "\" r=\"" . ($d / 2) . "\" fill=\"" . ACCENT . "\"/>";
        }
    }
    if ($rem) {
        $cx = 12 + ($i % $cols) * ($gw + 12); $cy = 34 + intdiv($i, $cols) * 58;
        $body .= "<rect x=\"{$cx}\" y=\"{$cy}\" width=\"{$gw}\" height=\"40\" rx=\"8\" fill=\"#fff7ed\" stroke=\"" . WARM . "\" stroke-width=\"1.3\" stroke-dasharray=\"5 3\"/>";
        for ($k = 0; $k < $rem; $k++) {
            $x = $cx + $gpad + $k * ($d + $gap) + $d / 2;
            $body .= "<circle cx=\"{$x}\" cy=\"" . ($cy + 20) . "\" r=\"" . ($d / 2) . "\" fill=\"" . WARM . "\"/>";
        }
        $body .= txt($cx + $gw / 2, $cy + 54, "remainder {$rem}", 11, WARM, 'middle', '600');
    }
    $body .= txt($w / 2, 20, $caption, 13, INK, 'middle', '600');
    return [$w, $h, $body];
}

/** Bracketing a non-square between two perfect squares. */
function rootBracket(int $value, int $lo, int $hi): array
{
    $w = 600; $h = 150; $pad = 60; $span = $w - 2 * $pad; $y = 82;
    $loSq = $lo * $lo; $hiSq = $hi * $hi;
    $px = fn($v) => $pad + ($v - $loSq) / ($hiSq - $loSq) * $span;
    $body  = "<line x1=\"{$pad}\" y1=\"{$y}\" x2=\"" . ($w - $pad) . "\" y2=\"{$y}\" stroke=\"" . INK . "\" stroke-width=\"1.5\"/>";
    foreach ([[$loSq, $lo, ACCENT], [$hiSq, $hi, ACCENT]] as [$sq, $root, $col]) {
        $x = $px($sq);
        $body .= "<line x1=\"{$x}\" y1=\"" . ($y - 12) . "\" x2=\"{$x}\" y2=\"" . ($y + 12) . "\" stroke=\"{$col}\" stroke-width=\"2.4\"/>";
        $body .= txt($x, $y - 20, (string) $sq, 13, $col, 'middle', '700');
        $body .= txt($x, $y + 32, "root {$root}", 11, $col);
    }
    $x = $px($value);
    $body .= "<line x1=\"{$x}\" y1=\"" . ($y - 22) . "\" x2=\"{$x}\" y2=\"" . ($y + 8) . "\" stroke=\"" . WARM . "\" stroke-width=\"2.4\" stroke-dasharray=\"4 3\"/>";
    $body .= "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"5\" fill=\"" . WARM . "\"/>";
    $body .= txt($x, $y - 30, (string) $value, 13, WARM, 'middle', '700');
    $body .= txt($w / 2, 24, "the square root of {$value} lies between {$lo} and {$hi}", 13, INK, 'middle', '600');
    return [$w, $h, $body];
}


