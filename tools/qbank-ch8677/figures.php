<?php
/**
 * Diagram generator for the chapter 8677 "Integers" question bank.
 *
 * Draws one purpose-built SVG per concept — number lines, arrays, Venn diagrams,
 * place-value strips — writes them under public/uploads/qbank/ch8677/ and
 * registers them in `lms_question_asset` against the questions where the picture
 * actually does work (the recall and the application items).
 *
 * SVGs rather than bitmaps: they are exact, scale to any screen, stay legible in
 * print, and are a few kilobytes each.
 *
 * Usage:  php figures.php --draw      write the SVG files only
 *         php figures.php --attach    write the files and insert asset rows
 */

const CHAPTER_ID = 8677;
const SUB_INST   = 341;
const EXTRACTION = 147;
const OUT_DIR    = 'C:/xampp/htdocs/next_lms_erp/public/uploads/qbank/ch8677';
const URL_BASE   = '/uploads/qbank/ch8677'; // root-relative: resolves on whatever host serves the app

$mode = $argv[1] ?? '--draw';

/* ------------------------------------------------------------ svg helpers */

const INK    = '#1e293b'; // axis / text
const MUTED  = '#94a3b8'; // secondary rule
const ACCENT = '#2563eb'; // the thing being shown
const WARM   = '#ea580c'; // the second quantity / the error
const GOOD   = '#15803d'; // the result
const PAPER  = '#ffffff';

function svg(int $w, int $h, string $body): string
{
    $f = 'font-family="ui-sans-serif,system-ui,Segoe UI,Arial,sans-serif"';
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" role="img" {$f}>
<rect width="{$w}" height="{$h}" fill="}PAPER{"/>
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


/* --------------------------------------------------------- concept figures */

$figures = [];

// helper to register a drawing
$reg = function (int $concept, array $drawn, string $alt, string $ocr) use (&$figures) {
    [$w, $h, $body] = $drawn;
    $figures[$concept] = ['w' => $w, 'h' => $h, 'svg' => svg($w, $h, $body), 'alt' => $alt, 'ocr' => $ocr];
};

/* topic 1 — adding and subtracting integers */
$reg(2367, numberLine(-5, 5),
    'A number line from -5 to 5 with every integer marked and zero highlighted.',
    'Integers: ..., -3, -2, -1, 0, 1, 2, 3, ... Zero is neither positive nor negative.');

$reg(2368, numberLine(-6, 6, [[-6, -1, 'negative integers', WARM], [1, 6, 'positive integers', GOOD]]),
    'A number line from -6 to 6 with the negative side and the positive side marked separately either side of zero.',
    'Negative integers lie to the left of zero; positive integers lie to the right of zero.');

$reg(2369, numberLine(-6, 4, [[-4, 2, '+6', ACCENT]]),
    'A number line showing a jump of six units to the right, starting at -4 and landing on 2.',
    '-4 + 6 = 2. Start at -4, move 6 to the right.');

$reg(2370, numberLine(-2, 8, [[2, 6, 'add the inverse: +4', GOOD]]),
    'A number line showing 2 minus negative 4 worked as a jump of four to the right, from 2 to 6.',
    '2 - (-4) = 2 + 4 = 6. Subtracting an integer means adding its inverse.');

$reg(2371, numberLine(-5, 5, [[0, 3, '3', GOOD], [0, -3, 'inverse: -3', WARM]]),
    'A number line showing 3 and -3 the same distance either side of zero.',
    'The inverse of 3 is -3, because 3 + (-3) = 0. Same distance from zero, opposite side.');

$reg(2372, numberLine(-4, 8, [[-2, 7, 'add the inverse: +9', GOOD]]),
    'A number line showing negative 2 minus negative 9 as a jump of nine to the right, from -2 to 7.',
    '-2 - (-9) = -2 + 9 = 7.');

$reg(2373, numberLine(40, 50, [[47, 50, 'rounds up to 50', ACCENT]]),
    'A number line from 40 to 50 with 47 marked past the halfway point of 45, rounding up to 50.',
    '47 is past the halfway mark 45, so it rounds up to 50.');

/* topic 2 — multiplying and dividing integers */
$reg(2294, numberLine(-30, 3, [[0, -27, '9 lots of -3', WARM]], ['step' => 3]),
    'A number line marked in threes showing nine steps of negative three from zero down to negative twenty-seven.',
    '9 x (-3) = -27. Nine repeats of -3 all move the same way, so the product is negative.');

$reg(2295, numberLine(-14, 2, [[0, -3, '-3', WARM], [-3, -6, '-3', WARM], [-6, -9, '-3', WARM], [-9, -12, '-3', WARM]]),
    'A number line showing four successive jumps of negative three from zero, reaching negative twelve.',
    '(-3) x 4 = (-3) + (-3) + (-3) + (-3) = -12. The running total is -3, -6, -9, -12.');

$reg(2296, grouping(12, 4, 'minus 12 shared into groups of 4 gives 3 groups: 4 x (-3) = -12'),
    'Twelve counters arranged in three equal groups of four, showing the division as a missing-factor multiplication.',
    '-12 divided by 4 = -3 because 4 x (-3) = -12.');

$reg(2297, grouping(45, 9, 'minus 45 shared into groups of 9 gives 5 groups, so the quotient is -5'),
    'Forty-five counters arranged in five equal groups of nine.',
    '-45 divided by 9 = -5. Unlike signs give a negative quotient.');

$reg(2298, numberLine(-8, 3, [[0, -6, 'bracket first: -6 + 2', MUTED], [0, -4, 'the bracket equals -4', ACCENT]]),
    'A number line showing the bracket in 3 times open bracket -6 plus 2 close bracket being worked out first, giving -4.',
    '3 x ((-6) + 2): the bracket gives -4 first, then 3 x (-4) = -12.');

/* topic 3 — lowest common multiples */
$reg(2299, multiplesTrack(4, 4, 40, 'the multiples of 4 continue without end: 4, 8, 12, 16, ...'),
    'A track showing the multiples of four up to forty.',
    'Multiples of 4: 4, 8, 12, 16, 20, 24, 28, 32, 36, 40, ...');

$reg(2300, multiplesTrack(4, 6, 48, 'the common multiples of 4 and 6 are 12, 24, 36, 48, ...'),
    'Two tracks of multiples, of four and of six, with the values common to both ringed.',
    'Common multiples of 4 and 6: 12, 24, 36, 48. The lowest is 12.');

$reg(2301, multiplesTrack(4, 6, 24, 'the lowest common multiple of 4 and 6 is 12'),
    'Two tracks of multiples, of four and of six, with twelve marked as the first shared value.',
    'LCM of 4 and 6 = 12, the first number appearing in both lists.');

$reg(2302, multiplesTrack(6, 10, 60, 'listing multiples gives the LCM of 6 and 10 as 30'),
    'Two tracks of multiples, of six and of ten, with thirty ringed as the first shared value.',
    'Multiples of 6: 6, 12, 18, 24, 30. Multiples of 10: 10, 20, 30. LCM = 30.');

/* topic 4 — highest common factors */
$reg(2303, factorRainbow(18, [1, 2, 3, 6, 9, 18], 'the factor pairs of 18: 1 x 18, 2 x 9 and 3 x 6'),
    'The factors of eighteen joined in pairs by arcs, each pair multiplying to eighteen.',
    'Factors of 18: 1, 2, 3, 6, 9, 18. Pairs: 1 x 18, 2 x 9, 3 x 6.');

$reg(2304, factorVenn(18, 27, [1, 2, 3, 6, 9, 18], [1, 3, 9, 27], 'the common factors of 18 and 27 are 1, 3 and 9'),
    'Two overlapping circles of factors, of eighteen and of twenty-seven, with one, three and nine in the overlap.',
    'Common factors of 18 and 27: 1, 3 and 9.');

$reg(2305, factorVenn(18, 27, [1, 2, 3, 6, 9, 18], [1, 3, 9, 27], 'the highest common factor of 18 and 27 is 9'),
    'Two overlapping circles of factors with the shared values one, three and nine shown, the largest being nine.',
    'HCF of 18 and 27 = 9, the largest value in the overlap.');

$reg(2306, factorVenn(16, 40, [1, 2, 4, 8, 16], [1, 2, 4, 5, 8, 10, 20, 40], 'listing factors gives the HCF of 16 and 40 as 8'),
    'Two overlapping circles of factors, of sixteen and of forty, with one, two, four and eight shared.',
    'Common factors of 16 and 40: 1, 2, 4, 8. HCF = 8.');

$reg(2307, factorVenn(16, 40, [1, 2, 4, 8, 16], [1, 2, 4, 5, 8, 10, 20, 40], 'dividing 16 and 40 by their HCF 8 gives 2 over 5'),
    'Two overlapping circles of factors with eight as the largest shared value, used to simplify sixteen fortieths.',
    'HCF of 16 and 40 is 8, so 16/40 simplifies to 2/5.');

/* topic 5 — tests for divisibility */
$reg(2308, grouping(30, 4, '30 shared into groups of 4 leaves 2 over, so 30 is not divisible by 4'),
    'Thirty counters arranged in seven groups of four with two counters left over.',
    '30 divided by 4 = 7 remainder 2, so 30 is not divisible by 4.');

$reg(2309, placeValue('87654', [4], 'divisibility by 2 reads the last digit only', 'last digit 4 is even, so 87654 is divisible by 2'),
    'The digits of 87654 in boxes with the final digit highlighted.',
    'A number is divisible by 2 when the last digit is 0, 2, 4, 6 or 8.');

$reg(2310, placeValue('87654', [0, 1, 2, 3, 4], 'divisibility by 3 adds every digit', '8+7+6+5+4 = 30, a multiple of 3'),
    'The digits of 87654 in boxes with all five highlighted, showing the digit sum.',
    'A number is divisible by 3 when the sum of its digits is a multiple of 3.');

$reg(2311, placeValue('87654', [3, 4], 'divisibility by 4 reads the last two digits', '54 divided by 4 = 13 remainder 2, so not divisible'),
    'The digits of 87654 with the final two highlighted as the two-digit number 54.',
    'A number is divisible by 4 when the number formed by the last two digits is divisible by 4.');

$reg(2312, placeValue('87654', [4], 'divisibility by 5 reads the last digit only', 'last digit 4 is neither 0 nor 5, so not divisible'),
    'The digits of 87654 with the final digit highlighted.',
    'A number is divisible by 5 when the last digit is 0 or 5.');

$reg(2313, placeValue('87654', [0, 1, 2, 3, 4], 'divisibility by 6 needs both tests to pass', 'even, and digits add to 30: divisible by 6'),
    'The digits of 87654 with all digits highlighted, showing both the evenness test and the digit sum test.',
    'A number is divisible by 6 when it is divisible by both 2 and 3.');

$reg(2314, placeValue('87654', [0, 1, 2, 3], 'test for 7: remove the last digit, subtract twice it', '8765 - 2x4 = 8757, which is divisible by 7'),
    'The digits of 87654 with the first four highlighted, showing the number left after removing the final digit.',
    'Divisibility by 7: 8765 - 2 x 4 = 8757, and 8757 = 7 x 1251.');

$reg(2315, placeValue('87654', [2, 3, 4], 'divisibility by 8 reads the last three digits', '654 divided by 8 = 81 remainder 6, so not divisible'),
    'The digits of 87654 with the final three highlighted as the three-digit number 654.',
    'A number is divisible by 8 when the number formed by the last three digits is divisible by 8.');

$reg(2316, placeValue('87654', [0, 1, 2, 3, 4], 'divisibility by 9 adds every digit', '8+7+6+5+4 = 30, which is not a multiple of 9'),
    'The digits of 87654 in boxes with all five highlighted, showing the digit sum of thirty.',
    'A number is divisible by 9 when the sum of its digits is divisible by 9. 30 is not.');

$reg(2317, placeValue('87654', [4], 'divisibility by 10 reads the last digit only', 'last digit 4 is not 0, so not divisible by 10'),
    'The digits of 87654 with the final digit highlighted.',
    'A number is divisible by 10 when the last digit is 0.');

$reg(2318, placeValue('87654', [0, 2, 4], 'divisibility by 11 alternates the positions', 'odd positions 4+6+8 = 18, even positions 5+7 = 12, difference 6'),
    'The digits of 87654 with the digits in odd positions highlighted, counting from the right.',
    'Divisibility by 11: sum of odd-position digits 18, sum of even-position digits 12, difference 6.');

/* topic 6 — square roots and cube roots */
$reg(2319, squareArray(5, '5 x 5 = 25, so 25 is a square number'),
    'A five by five array of twenty-five squares.',
    'Square numbers come from a square array: 1, 4, 9, 16, 25, ...');

$reg(2320, inverseLoop('4', '16', 'squaring: 4 x 4', 'square root of 16', 'squaring and taking a square root undo each other'),
    'A diagram showing four squared giving sixteen, and the square root of sixteen returning four.',
    '4 squared = 16 is equivalent to 4 = the square root of 16.');

$reg(2321, cubeModel(3, '3 x 3 x 3 = 27, so 27 is a cube number'),
    'A stack of three layers of three by three cubes, twenty-seven in all.',
    'Cube numbers come from a cube: 1, 8, 27, 64, ...');

$reg(2322, inverseLoop('2', '8', 'cubing: 2 x 2 x 2', 'cube root of 8', 'cubing and taking a cube root undo each other'),
    'A diagram showing two cubed giving eight, and the cube root of eight returning two.',
    '2 cubed = 8 is equivalent to 2 = the cube root of 8.');

$reg(2323, rootBracket(79, 8, 9),
    'A number line between sixty-four and eighty-one with seventy-nine marked close to eighty-one.',
    'The square root of 79 lies between 8 and 9, and closer to 9 because 79 is near 81.');

/* ------------------------------------------------------------ write files */

if (!is_dir(OUT_DIR)) { mkdir(OUT_DIR, 0775, true); }

$written = [];
foreach ($figures as $concept => $f) {
    $svg = str_replace('}PAPER{', PAPER, $f['svg']);
    $path = OUT_DIR . "/concept-{$concept}.svg";
    file_put_contents($path, $svg);
    $written[$concept] = [
        'path'   => $path,
        'file'   => "concept-{$concept}.svg",
        'url'    => URL_BASE . "/concept-{$concept}.svg",
        'sha256' => hash('sha256', $svg),
        'bytes'  => strlen($svg),
        'w'      => $f['w'],
        'h'      => $f['h'],
        'alt'    => $f['alt'],
        'ocr'    => $f['ocr'],
    ];
}
echo "figures written : " . count($written) . " into " . OUT_DIR . "\n";
echo "total bytes     : " . array_sum(array_column($written, 'bytes')) . "\n";

if ($mode !== '--attach') { exit(0); }

/* ---------------------------------------------------------- attach to rows */

$pdo = new PDO('mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4', 'vivek_user', 'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// The picture earns its place on the items where a learner is reading the rule
// or carrying out the procedure: the recall item and the application items.
$pick = $pdo->prepare(
    "select id from lms_question_master
      where chapter_id = ? and concept_id = ? and g_bloom in ('Remember','Apply')
      order by id limit 3"
);
$ins = $pdo->prepare(
    "insert into lms_question_asset
       (question_id, sub_institute_id, extraction_id, role, option_label, asset_sha256,
        source_path, stored_url, mime, width, height, byte_size, alt_text, ocr_text, source_page, ordinal)
     values (?,?,?,'stem',null,?,?,?,'image/svg+xml',?,?,?,?,?,null,0)"
);

$rows = 0;
$pdo->beginTransaction();
try {
    foreach ($written as $concept => $f) {
        $pick->execute([CHAPTER_ID, $concept]);
        foreach ($pick->fetchAll(PDO::FETCH_COLUMN) as $qid) {
            $ins->execute([
                $qid, SUB_INST, EXTRACTION, $f['sha256'],
                'uploads/qbank/ch8677/' . $f['file'], $f['url'],
                $f['w'], $f['h'], $f['bytes'], $f['alt'], $f['ocr'],
            ]);
            $rows++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "attach failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
echo "asset rows      : {$rows}\n";
