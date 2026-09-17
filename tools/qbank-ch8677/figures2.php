<?php
/**
 * Question-specific diagrams for the chapter 8677 question bank.
 *
 * The first pass attached one reference figure per concept, which meant a stem
 * about 8 + (-3) could carry a picture of -4 + 6. This pass replaces that: every
 * figure is drawn from the numbers in the stem it hangs on, so the picture and
 * the question always agree. Concept-level reference figures are kept only on the
 * recall item, where illustrating the rule is the point.
 *
 * Usage: php figures2.php --draw | --attach
 */

require __DIR__ . '/svglib.php';

const CHAPTER_ID = 8677;
const SUB_INST   = 341;
const EXTRACTION = 147;
const OUT_DIR    = 'C:/xampp/htdocs/next_lms_erp/public/uploads/qbank/ch8677';
// Absolute, built from the backend APP_URL. The frontend is a separate origin
// (Next.js, NEXT_PUBLIC_API_BASE_URL_DEV=http://127.0.0.1:8000), so a
// root-relative URL would resolve against the frontend host, which does not
// serve public/. Re-run this on each host so the URLs match that host APP_URL.
$appUrl = 'http://127.0.0.1:8000';
foreach (file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^APP_URL=(.+)$/', trim($line), $mm)) { $appUrl = trim($mm[1], " \"'"); }
}
define('URL_BASE', rtrim($appUrl, '/') . '/uploads/qbank/ch8677');

$mode = $argv[1] ?? '--draw';

$pdo = new PDO('mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4', 'vivek_user', 'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$questions = $pdo->query(
    "select id, concept_id, g_bloom, question_title
       from lms_question_master where chapter_id = " . CHAPTER_ID . " order by id"
)->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------------------------------- per-question matchers */

/** Tidy a stem for matching: strip the parenthesised-negative form. */
function norm(string $s): string
{
    return preg_replace('/\s+/', ' ', $s);
}

/**
 * Decide the figure for one question, or null when a picture would add nothing.
 * Returns [key, width, height, svgBody, altText, ocrText].
 */
function figureFor(array $q): ?array
{
    $t  = norm($q['question_title']);
    $c  = (int) $q['concept_id'];

    // --- integer addition / subtraction on a number line -------------------
    // Only for the integer-arithmetic concepts, and only when the stem is asking
    // for the calculation. Arithmetic quoted inside an assertion or a reported
    // student claim is being judged, not worked, so a number line there would
    // illustrate the wrong thing - or, on a divisibility item, something
    // unrelated that merely looks like a sum.
    $arithmeticConcepts = [2367,2368,2369,2370,2371,2372,2373,2294,2295,2296,2297,2298];
    $asksForTheSum = preg_match('/work out|use a number line|what is the value|estimate|do these (additions|subtractions)/i', $t)
        && !preg_match('/assertion|says:|writes:|claims/i', $t);
    if (in_array($c, $arithmeticConcepts, true) && $asksForTheSum
        && (preg_match('/(-?\d+)\s*([+-])\s*\(\s*(-?\d+)\s*\)/', $t, $m)
        || preg_match('/\b(-?\d+)\s*([+-])\s*(-?\d+)\b/', $t, $m))) {
        $a = (int) $m[1]; $op = $m[2]; $b = (int) $m[3];
        if (abs($a) <= 30 && abs($b) <= 30) {
            // subtraction becomes addition of the inverse
            $move  = $op === '-' ? -$b : $b;
            $end   = $a + $move;
            $label = $op === '-'
                ? 'add the inverse: ' . ($move >= 0 ? '+' : '') . $move
                : ($move >= 0 ? '+' : '') . $move;
            $lo = min($a, $end, 0) - 2; $hi = max($a, $end, 0) + 2;
            if ($hi - $lo <= 26) {
                $col = $move >= 0 ? GOOD : WARM;
                [$w, $h, $body] = numberLine($lo, $hi, [[$a, $end, $label, $col]]);
                $expr = $op === '-' ? "{$a} - ({$b})" : "{$a} + ({$b})";
                return ["q{$q['id']}", $w, $h, $body,
                    "A number line showing the move from {$a} to {$end}, illustrating {$expr}.",
                    "{$expr} = {$end}. Start at {$a} and move " . abs($move) . ' units to the ' . ($move >= 0 ? 'right' : 'left') . '.'];
            }
        }
    }

    // --- squares ------------------------------------------------------------
    if (preg_match('/\b(\d{1,2}) squared\b/i', $t, $m)) {
        $n = (int) $m[1];
        if ($n >= 1 && $n <= 15) {
            [$w, $h, $body] = squareArray($n, "{$n} x {$n} = " . $n * $n);
            return ["q{$q['id']}", $w, $h, $body,
                "A {$n} by {$n} array of squares, showing {$n} squared.",
                "{$n} squared = {$n} x {$n} = " . $n * $n . '.'];
        }
    }

    // --- cubes --------------------------------------------------------------
    if (preg_match('/\b(\d) cubed\b/i', $t, $m)) {
        $n = (int) $m[1];
        if ($n >= 1 && $n <= 5) {
            [$w, $h, $body] = cubeModel($n, "{$n} x {$n} x {$n} = " . $n ** 3);
            return ["q{$q['id']}", $w, $h, $body,
                "A cube built from {$n} layers of {$n} by {$n} blocks.",
                "{$n} cubed = {$n} x {$n} x {$n} = " . $n ** 3 . '.'];
        }
    }

    // --- estimating a square root of a non-square ---------------------------
    if ($c === 2323 && preg_match('/square root of (\d+)/i', $t, $m)) {
        $v = (int) $m[1];
        $lo = (int) floor(sqrt($v));
        if ($lo * $lo !== $v && $lo >= 1 && $v <= 400) {
            [$w, $h, $body] = rootBracket($v, $lo, $lo + 1);
            return ["q{$q['id']}", $w, $h, $body,
                "A number line between " . $lo * $lo . " and " . ($lo + 1) ** 2 . " with {$v} marked between them.",
                "The square root of {$v} lies between {$lo} and " . ($lo + 1) . '.'];
        }
    }

    // --- divisibility: highlight exactly the digits the rule reads ----------
    static $ruleDigits = [
        2309 => ['tail' => 1, 'rule' => 'divisibility by 2 reads the last digit'],
        2311 => ['tail' => 2, 'rule' => 'divisibility by 4 reads the last two digits'],
        2312 => ['tail' => 1, 'rule' => 'divisibility by 5 reads the last digit'],
        2315 => ['tail' => 3, 'rule' => 'divisibility by 8 reads the last three digits'],
        2317 => ['tail' => 1, 'rule' => 'divisibility by 10 reads the last digit'],
        2310 => ['all' => true, 'div' => 3, 'rule' => 'divisibility by 3 adds every digit'],
        2316 => ['all' => true, 'div' => 9, 'rule' => 'divisibility by 9 adds every digit'],
        2318 => ['alt' => true, 'rule' => 'divisibility by 11 alternates the positions'],
    ];
    if (isset($ruleDigits[$c]) && preg_match('/\b(\d{3,6})\b/', $t, $m)) {
        $d = $m[1];
        $n = strlen($d);
        $spec = $ruleDigits[$c];
        if (!empty($spec['tail'])) {
            $k = $spec['tail'];
            $hot = range($n - $k, $n - 1);
            $part = substr($d, -$k);
            $divisor = [2309 => 2, 2311 => 4, 2312 => 5, 2315 => 8, 2317 => 10][$c];
            $ok = ((int) $part) % $divisor === 0;
            $note = $k === 1
                ? "last digit {$part}: " . ($ok ? "divisible by {$divisor}" : "not divisible by {$divisor}")
                : "{$part} divided by {$divisor} " . ($ok ? '= ' . intdiv((int) $part, $divisor) . ', divisible' : 'leaves ' . ((int) $part % $divisor) . ', not divisible');
        } elseif (!empty($spec['all'])) {
            $hot = range(0, $n - 1);
            $sum = array_sum(str_split($d));
            $div = $spec['div'];
            $note = implode(' + ', str_split($d)) . " = {$sum}, " . ($sum % $div === 0 ? "a multiple of {$div}" : "not a multiple of {$div}");
        } else { // alternating positions, counted from the right
            $hot = [];
            for ($i = $n - 1; $i >= 0; $i -= 2) { $hot[] = $i; }
            $odd = 0; $even = 0;
            for ($i = 0; $i < $n; $i++) {
                $fromRight = $n - $i; // 1-based position from the right
                if ($fromRight % 2 === 1) { $odd += (int) $d[$i]; } else { $even += (int) $d[$i]; }
            }
            $diff = $odd - $even;
            $note = "odd positions {$odd}, even positions {$even}, difference {$diff}";
        }
        [$w, $h, $body] = placeValue($d, $hot, $spec['rule'], $note);
        return ["q{$q['id']}", $w, $h, $body,
            "The digits of {$d} in boxes, with the digits the rule reads highlighted.",
            "{$d}: {$note}."];
    }

    // --- LCM of two stated numbers ------------------------------------------
    if (in_array($c, [2300, 2301, 2302], true)
        && preg_match('/(?:of|between)\s+(\d{1,2})\s+and\s+(\d{1,2})\b/i', $t, $m)) {
        $a = (int) $m[1]; $b = (int) $m[2];
        if ($a > 1 && $b > 1 && $a <= 20 && $b <= 20 && $a !== $b) {
            $lcm = $a * $b / gcdInt($a, $b);
            if ($lcm <= 90) {
                [$w, $h, $body] = multiplesTrack($a, $b, (int) min($lcm * 2, 90), "the lowest common multiple of {$a} and {$b} is {$lcm}");
                return ["q{$q['id']}", $w, $h, $body,
                    "Two tracks of multiples, of {$a} and of {$b}, with the shared values ringed.",
                    "LCM of {$a} and {$b} = {$lcm}."];
            }
        }
    }

    // --- HCF of two stated numbers ------------------------------------------
    if (in_array($c, [2303, 2304, 2305, 2306, 2307], true)
        && preg_match('/(?:of|between)\s+(\d{1,3})\s+and\s+(\d{1,3})\b/i', $t, $m)) {
        $a = (int) $m[1]; $b = (int) $m[2];
        if ($a > 1 && $b > 1 && $a <= 100 && $b <= 100 && $a !== $b) {
            $fa = factorsOf($a); $fb = factorsOf($b);
            if (count($fa) <= 12 && count($fb) <= 12) {
                $hcf = gcdInt($a, $b);
                [$w, $h, $body] = factorVenn($a, $b, $fa, $fb, "the highest common factor of {$a} and {$b} is {$hcf}");
                return ["q{$q['id']}", $w, $h, $body,
                    "Two overlapping circles of factors, of {$a} and of {$b}, with the shared factors in the overlap.",
                    "HCF of {$a} and {$b} = {$hcf}."];
            }
        }
    }

    return null;
}

function gcdInt(int $a, int $b): int { while ($b) { [$a, $b] = [$b, $a % $b]; } return $a; }
function factorsOf(int $n): array
{
    $f = [];
    for ($i = 1; $i * $i <= $n; $i++) {
        if ($n % $i === 0) { $f[] = $i; if ($i !== intdiv($n, $i)) { $f[] = intdiv($n, $i); } }
    }
    sort($f);
    return $f;
}

/* ------------------------------------------------------------------ build */

if (!is_dir(OUT_DIR)) { mkdir(OUT_DIR, 0775, true); }

$plan = [];      // question_id => figure record
$byConcept = []; // concept_id => [question ids, in order]
foreach ($questions as $q) {
    $byConcept[(int) $q['concept_id']][] = $q;
}

foreach ($questions as $q) {
    $fig = figureFor($q);
    if ($fig === null) { continue; }
    [$key, $w, $h, $body, $alt, $ocr] = $fig;
    $svg  = svg($w, $h, $body);
    $file = "{$key}.svg";
    file_put_contents(OUT_DIR . "/{$file}", $svg);
    $plan[(int) $q['id']] = [
        'file' => $file, 'url' => URL_BASE . "/{$file}",
        'sha' => hash('sha256', $svg), 'bytes' => strlen($svg),
        'w' => $w, 'h' => $h, 'alt' => $alt, 'ocr' => $ocr,
    ];
}

// Concept reference figure on the recall item, where illustrating the rule is
// the whole point and no specific calculation is claimed.
$refCount = 0;
foreach ($byConcept as $concept => $qs) {
    $refFile = "concept-{$concept}.svg";
    if (!is_file(OUT_DIR . "/{$refFile}")) { continue; }
    foreach ($qs as $q) {
        $id = (int) $q['id'];
        if ($q['g_bloom'] !== 'Remember' || isset($plan[$id])) { continue; }
        $svg = file_get_contents(OUT_DIR . "/{$refFile}");
        $plan[$id] = [
            'file' => $refFile, 'url' => URL_BASE . "/{$refFile}",
            'sha' => hash('sha256', $svg), 'bytes' => strlen($svg),
            'w' => null, 'h' => null,
            'alt' => 'Reference diagram for this concept.',
            'ocr' => 'Worked reference example for the rule this question asks about.',
        ];
        $refCount++;
        break; // one recall item per concept
    }
}

// carry the stored dimensions for the reference figures
foreach ($plan as $id => &$p) {
    if ($p['w'] === null) {
        $x = simplexml_load_file(OUT_DIR . '/' . $p['file']);
        $p['w'] = (int) $x['width']; $p['h'] = (int) $x['height'];
    }
}
unset($p);

echo "question-specific figures : " . (count($plan) - $refCount) . "\n";
echo "concept reference figures : {$refCount}\n";
echo "questions illustrated     : " . count($plan) . "\n";

if ($mode !== '--attach') { exit(0); }

$pdo->beginTransaction();
try {
    // clear the first pass for this chapter
    $pdo->exec(
        "delete a from lms_question_asset a
           join lms_question_master q on q.id = a.question_id
          where q.chapter_id = " . CHAPTER_ID
    );
    $ins = $pdo->prepare(
        "insert into lms_question_asset
           (question_id, sub_institute_id, extraction_id, role, option_label, asset_sha256,
            source_path, stored_url, mime, width, height, byte_size, alt_text, ocr_text, source_page, ordinal)
         values (?,?,?,'stem',null,?,?,?,'image/svg+xml',?,?,?,?,?,null,0)"
    );
    foreach ($plan as $qid => $p) {
        $ins->execute([
            $qid, SUB_INST, EXTRACTION, $p['sha'],
            'uploads/qbank/ch8677/' . $p['file'], $p['url'],
            $p['w'], $p['h'], $p['bytes'], $p['alt'], $p['ocr'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'attach failed, rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "asset rows written        : " . count($plan) . "\n";
