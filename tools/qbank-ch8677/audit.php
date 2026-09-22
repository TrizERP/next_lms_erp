<?php
/**
 * Arithmetic audit of the chapter 8677 question bank.
 *
 * Re-derives every checkable numeric claim in the stems, options, rationales,
 * explanations and model answers, and reports any that do not hold. Catching a
 * wrong answer key matters more here than catching a typo: a keyed distractor
 * teaches the misconception it was written to expose.
 */

const CHAPTER_ID = 8677;

$pdo = new PDO('mysql:host=202.47.117.220;dbname=vivek_erp;charset=utf8mb4', 'vivek_user', 'vivek@sql',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$rows = $pdo->query(
    "select id, concept_id, question_title, answer from lms_question_master
      where chapter_id = " . CHAPTER_ID . " order by id"
)->fetchAll(PDO::FETCH_ASSOC);

function gcdInt(int $a, int $b): int { $a = abs($a); $b = abs($b); while ($b) { [$a, $b] = [$b, $a % $b]; } return $a; }
function lcmInt(int $a, int $b): int { return intdiv(abs($a * $b), gcdInt($a, $b)); }

$problems = [];
$checked = 0;

/** Every piece of prose attached to a question, labelled by where it came from. */
function surfaces(array $r): array
{
    $out = [['stem', $r['question_title']]];
    $a = json_decode($r['answer'], true) ?: [];
    foreach (($a['options'] ?? []) as $o) {
        $tag = 'option ' . ($o['label'] ?? '?') . (!empty($o['is_correct']) ? ' [KEYED]' : '');
        $out[] = [$tag, (string) ($o['text'] ?? '')];
        $out[] = [$tag . ' rationale', (string) ($o['rationale'] ?? '')];
    }
    foreach (['explanation', 'remediation', 'model_answer'] as $k) {
        if (!empty($a[$k])) { $out[] = [$k, (string) $a[$k]]; }
    }
    foreach (($a['marking_points'] ?? []) as $i => $mp) {
        $out[] = ["marking point " . ($i + 1), (string) ($mp['criterion'] ?? '')];
        foreach (($mp['accept'] ?? []) as $acc) { $out[] = ["marking point " . ($i + 1) . " accept", (string) $acc]; }
    }
    return $out;
}

foreach ($rows as $r) {
    foreach (surfaces($r) as [$where, $text]) {
        if ($text === '') { continue; }
        $t = preg_replace('/\s+/', ' ', $text);

        $flag = function (string $claim, string $truth) use (&$problems, $r, $where, $t) {
            $pos = strpos($t, $claim);
            if ($pos !== false) {
                $pre  = substr($t, max(0, $pos - 2), min(2, $pos));
                $post = substr($t, $pos + strlen($claim), 2);
                // part of a longer expression: not a standalone claim
                if (preg_match('/[-+x\/=] $/', $pre) || preg_match('/^ ?[-+x\/]/', $post)) { return; }
            }
            $problems[] = [
                'id' => $r['id'], 'concept' => $r['concept_id'],
                'where' => $where, 'claim' => $claim, 'truth' => $truth,
            ];
        };

        // HCF / highest common factor of A and B is C
        if (preg_match_all('/(?:HCF|highest common factor) of (\d{1,4}) and (\d{1,4}) (?:is|=) (\d{1,5})/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = gcdInt((int) $m[1], (int) $m[2]);
                if ($real !== (int) $m[3]) { $flag($m[0], "HCF({$m[1]},{$m[2]}) = {$real}"); }
            }
        }
        // LCM / lowest common multiple of A and B is C
        if (preg_match_all('/(?:LCM|lowest common multiple) of (\d{1,4}) and (\d{1,4}) (?:is|=) (\d{1,6})/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = lcmInt((int) $m[1], (int) $m[2]);
                if ($real !== (int) $m[3]) { $flag($m[0], "LCM({$m[1]},{$m[2]}) = {$real}"); }
            }
        }
        // A x B = C
        if (preg_match_all('/(-?\d{1,5}) ?x ?(-?\d{1,5}) = (-?\d{1,7})/', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = (int) $m[1] * (int) $m[2];
                if ($real !== (int) $m[3]) { $flag($m[0], "= {$real}"); }
            }
        }
        // A x B x C = D
        if (preg_match_all('/(-?\d{1,4}) ?x ?(-?\d{1,4}) ?x ?(-?\d{1,4}) = (-?\d{1,7})/', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = (int) $m[1] * (int) $m[2] * (int) $m[3];
                if ($real !== (int) $m[4]) { $flag($m[0], "= {$real}"); }
            }
        }
        // A divided by B is C  (exact only)
        if (preg_match_all('/(-?\d{1,6}) divided by (-?\d{1,4}) (?:is|=) (-?\d{1,6})(?![\d.])(?! (?:remainder|with|r\b))/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $b = (int) $m[2];
                if ($b === 0) { continue; }
                $a = (int) $m[1];
                if ($a % $b !== 0) { continue; } // a remainder form is handled below
                $checked++;
                $real = intdiv($a, $b);
                if ($real !== (int) $m[3]) { $flag($m[0], "= {$real}"); }
            }
        }
        // A divided by B is C remainder D
        if (preg_match_all('/(-?\d{1,6}) divided by (\d{1,4}) is (\d{1,6}) (?:remainder|with a remainder of|r) (\d{1,4})/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $a = (int) $m[1]; $b = (int) $m[2];
                $q = intdiv(abs($a), $b); $rm = abs($a) % $b;
                if ($q !== (int) $m[3] || $rm !== (int) $m[4]) { $flag($m[0], "= {$q} remainder {$rm}"); }
            }
        }
        // N squared is S
        if (preg_match_all('/(\d{1,3}) squared (?:is|=) (\d{1,6})/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = (int) $m[1] ** 2;
                if ($real !== (int) $m[2]) { $flag($m[0], "= {$real}"); }
            }
        }
        // N cubed is C
        if (preg_match_all('/(\d{1,3}) cubed (?:is|=) (\d{1,7})/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $real = (int) $m[1] ** 3;
                if ($real !== (int) $m[2]) { $flag($m[0], "= {$real}"); }
            }
        }
        // the square root of N is R
        if (preg_match_all('/square root of (\d{1,6}) is (\d{1,4})(?![\d.])/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $n = (int) $m[1]; $claim = (int) $m[2];
                if ($claim * $claim !== $n) { $flag($m[0], "root of {$n} is not {$claim} (" . $claim * $claim . " != {$n})"); }
            }
        }
        // the cube root of N is R
        if (preg_match_all('/cube root of (\d{1,7}) is (\d{1,4})(?![\d.])/i', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $checked++;
                $n = (int) $m[1]; $claim = (int) $m[2];
                if ($claim ** 3 !== $n) { $flag($m[0], "cube root of {$n} is not {$claim} (" . $claim ** 3 . " != {$n})"); }
            }
        }
        // digit sums: "a + b + c = S"
        if (preg_match_all('/(?<![-(\d.])(\d(?: \+ \d){1,7}) = (\d{1,3})\b/', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $parts = array_map('intval', preg_split('/ ?\+ ?/', $m[1]));
                $checked++;
                $real = array_sum($parts);
                if ($real !== (int) $m[2]) { $flag($m[0], "= {$real}"); }
            }
        }
    }
}

echo "numeric claims checked : {$checked}\n";
echo "questions scanned      : " . count($rows) . "\n";
echo "problems found         : " . count($problems) . "\n\n";

foreach ($problems as $p) {
    echo "q{$p['id']} (concept {$p['concept']}) {$p['where']}\n";
    echo "   claims : {$p['claim']}\n";
    echo "   truth  : {$p['truth']}\n\n";
}
