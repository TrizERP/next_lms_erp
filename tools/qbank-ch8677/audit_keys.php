<?php
/**
 * Semantic key check: stems that report someone computing a value, where the
 * keyed option then passes judgement on it. The arithmetic audit cannot see
 * these, because the wrong number sits in the stem and the defect is the key
 * agreeing with it.
 */

$pdo = new PDO('mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4', 'vivek_user', 'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function g(int $a, int $b): int { $a = abs($a); $b = abs($b); while ($b) { [$a, $b] = [$b, $a % $b]; } return $a; }

$rows = $pdo->query(
    "select id, concept_id, question_title, answer from lms_question_master
      where chapter_id = 8677 order by id"
)->fetchAll(PDO::FETCH_ASSOC);

$examined = 0; $defects = [];

foreach ($rows as $r) {
    $t = preg_replace('/\s+/', ' ', $r['question_title']);
    if (!preg_match('/(?:finds|calculates|works out|gets|to be)/i', $t)) { continue; }
    if (!preg_match('/(HCF|LCM|highest common factor|lowest common multiple) of (\d{1,4}) and (\d{1,4}) (?:to be|is|as) (\d{1,5})/i', $t, $m)) { continue; }

    $a = (int) $m[2]; $b = (int) $m[3]; $claim = (int) $m[4];
    $isHcf = stripos($m[1], 'h') === 0;
    $real  = $isHcf ? g($a, $b) : intdiv($a * $b, g($a, $b));

    $env = json_decode($r['answer'], true) ?: [];
    $keyed = '';
    foreach (($env['options'] ?? []) as $o) {
        if (!empty($o['is_correct'])) { $keyed = trim((string) $o['text']); }
    }
    if ($keyed === '') { continue; }
    $examined++;

    $endorses = (bool) preg_match('/^(the answer is right|correct,|both the answer and)/i', $keyed);
    $rejects  = (bool) preg_match('/^(the answer is wrong|incorrect)/i', $keyed);

    $bad = ($claim !== $real && $endorses) || ($claim === $real && $rejects);
    $line = sprintf("q%s c%s  %s(%d,%d): stem says %d, true %d\n      keyed: %s\n",
        $r['id'], $r['concept_id'], strtoupper($isHcf ? 'HCF' : 'LCM'), $a, $b, $claim, $real, substr($keyed, 0, 96));
    if ($bad) { $defects[] = $line; } else { echo "    ok   " . $line; }
}

echo "\ncandidates examined : {$examined}\n";
echo "defects             : " . count($defects) . "\n\n";
foreach ($defects as $d) { echo "*** DEFECT " . $d . "\n"; }
