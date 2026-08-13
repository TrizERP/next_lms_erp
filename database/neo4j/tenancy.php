<?php
// Normalised tenancy detection: strip underscores + lowercase, so
// sub_institute_id / SubInstituteId / subInstituteID all collapse to one key.
$inv = json_decode(file_get_contents(__DIR__ . '/inventory.json'), true);
$norm = fn($s) => strtolower(str_replace('_', '', $s));

$rows = [];
$stat = ['sii' => 0, 'syear' => 0, 'both' => 0, 'neither_nonempty' => 0];
foreach ($inv as $t) {
    $map = [];
    foreach (array_keys($t['columns']) as $c) $map[$norm($c)] = $c;
    $sii = $map['subinstituteid'] ?? null;
    $sy  = $map['syear'] ?? ($map['academicyearid'] ?? ($map['academicyear'] ?? null));
    if ($sii) $stat['sii']++;
    if ($sy) $stat['syear']++;
    if ($sii && $sy) $stat['both']++;
    // candidate derivation FKs when tenancy is absent
    $fks = [];
    foreach (array_keys($t['columns']) as $c) {
        if (preg_match('/(^|_)(id)$/i', $c) && $norm($c) !== 'id') $fks[] = $c;
    }
    $rows[$t['table']] = ['sii' => $sii, 'syear' => $sy, 'fks' => $fks];
    if (!$sii && $t['exact_rows'] > 0) $stat['neither_nonempty']++;
}
file_put_contents(__DIR__ . '/tenancy.json', json_encode($rows, JSON_PRETTY_PRINT));
echo "tables=" . count($inv) . "\n";
foreach ($stat as $k => $v) echo "  $k = $v\n";

// non-standard spellings actually in use
$spell = [];
foreach ($rows as $tn => $r) if ($r['sii'] && $r['sii'] !== 'sub_institute_id') $spell[$r['sii']][] = $tn;
echo "\nNON-STANDARD sub_institute_id spellings:\n";
foreach ($spell as $s => $ts) echo "  $s  ->  " . count($ts) . " tables: " . implode(', ', array_slice($ts, 0, 12)) . "\n";
$spell2 = [];
foreach ($rows as $tn => $r) if ($r['syear'] && $r['syear'] !== 'syear') $spell2[$r['syear']][] = $tn;
echo "\nNON-STANDARD syear spellings:\n";
foreach ($spell2 as $s => $ts) echo "  $s  ->  " . count($ts) . " tables: " . implode(', ', array_slice($ts, 0, 12)) . "\n";
