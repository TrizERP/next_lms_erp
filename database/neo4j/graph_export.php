<?php
// PHASE 0 AMENDMENT — export the node/rel sets Phase 0 wrongly skipped as "re-derivable".
// STRICTLY READ-ONLY: MATCH/RETURN only. Same CSV format as the 2026-08-10 backup.
require dirname(dirname(__DIR__)) . '/vendor/autoload.php';
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$OUT = dirname(dirname(__DIR__)) . '/docs/neo4j-backup-2026-08-10';
$env = [];
foreach (file(dirname(dirname(__DIR__)) . '/.env') as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}
$client = ClientBuilder::create()
    ->withDriver('x', $env['NEO4J_URI'], Authenticate::basic($env['NEO4J_USERNAME'], $env['NEO4J_PASSWORD']))
    ->build();

$KEYISH = '/(Id$|_id$|_name$|^name$|^title$)/i';
$scalar = function ($v) {
    if ($v === null) return '';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_array($v)) return json_encode($v);
    if (is_object($v)) return method_exists($v, 'toArray') ? json_encode($v->toArray()) : (string)$v;
    return (string)$v;
};

// ── NODES ──
function exportNodes($client, $label, $OUT, $scalar) {
    $keys = [];
    foreach ($client->run("MATCH (n:$label) UNWIND keys(n) AS k RETURN DISTINCT k ORDER BY k") as $r) $keys[] = $r->get('k');
    $total = $client->run("MATCH (n:$label) RETURN count(n) AS c")->first()->get('c');
    $file = "$OUT/nodes_$label.csv";
    $fh = fopen($file, 'w');
    fputcsv($fh, array_merge(['_neo4jInternalId'], $keys));
    $written = 0; $batch = 5000;
    for ($skip = 0; $skip < $total; $skip += $batch) {
        $res = $client->run("MATCH (n:$label) RETURN id(n) AS iid, n ORDER BY id(n) SKIP $skip LIMIT $batch");
        foreach ($res as $rec) {
            $props = $rec->get('n')->getProperties()->toArray();
            $row = [$rec->get('iid')];
            foreach ($keys as $k) $row[] = $scalar($props[$k] ?? null);
            fputcsv($fh, $row);
            $written++;
        }
        fwrite(STDERR, "  $label $written/$total\n");
    }
    fclose($fh);
    printf("%-28s %8d rows  %6.1f MB  keys: %s\n", "nodes_$label.csv", $written,
        filesize($file) / 1048576, implode(',', $keys));
    return [$written, $total];
}

// ── RELATIONSHIPS ──
function exportRels($client, $type, $OUT, $scalar, $KEYISH) {
    $keys = [];
    foreach ($client->run("MATCH ()-[r:$type]->() UNWIND keys(r) AS k RETURN DISTINCT k ORDER BY k") as $r) $keys[] = $r->get('k');
    $total = $client->run("MATCH ()-[r:$type]->() RETURN count(r) AS c")->first()->get('c');
    $file = "$OUT/rels_$type.csv";
    $fh = fopen($file, 'w');
    fputcsv($fh, array_merge(
        ['_relInternalId','_fromInternalId','_fromLabels','_fromKeyProps','_toInternalId','_toLabels','_toKeyProps'],
        $keys));
    $written = 0; $batch = 5000;
    for ($skip = 0; $skip < $total; $skip += $batch) {
        $res = $client->run("MATCH (a)-[r:$type]->(b)
            RETURN id(r) AS rid, id(a) AS aid, labels(a) AS al, a, id(b) AS bid, labels(b) AS bl, b, r
            ORDER BY id(r) SKIP $skip LIMIT $batch");
        foreach ($res as $rec) {
            $ap = $rec->get('a')->getProperties()->toArray();
            $bp = $rec->get('b')->getProperties()->toArray();
            $ak = []; foreach ($ap as $k => $v) if (preg_match($KEYISH, $k)) $ak[$k] = $scalar($v);
            $bk = []; foreach ($bp as $k => $v) if (preg_match($KEYISH, $k)) $bk[$k] = $scalar($v);
            $rp = $rec->get('r')->getProperties()->toArray();
            $row = [
                $rec->get('rid'),
                $rec->get('aid'), implode(':', $rec->get('al')->toArray()), json_encode($ak),
                $rec->get('bid'), implode(':', $rec->get('bl')->toArray()), json_encode($bk),
            ];
            foreach ($keys as $k) $row[] = $scalar($rp[$k] ?? null);
            fputcsv($fh, $row);
            $written++;
        }
        fwrite(STDERR, "  $type $written/$total\n");
    }
    fclose($fh);
    printf("%-28s %8d rows  %6.1f MB  props: %s\n", "rels_$type.csv", $written,
        filesize($file) / 1048576, $keys ? implode(',', $keys) : '(none)');
    return [$written, $total];
}

echo "=== PHASE 0 AMENDMENT EXPORT ===\n";
$manifest = [];
$manifest['Chapter']      = exportNodes($client, 'Chapter', $OUT, $scalar);
$manifest['Question']     = exportNodes($client, 'Question', $OUT, $scalar);
$manifest['BELONGS_TO']   = exportRels($client, 'BELONGS_TO', $OUT, $scalar, $KEYISH);
$manifest['HAS_CHAPTER']  = exportRels($client, 'HAS_CHAPTER', $OUT, $scalar, $KEYISH);

echo "\n=== ROW-COUNT VERIFICATION ===\n";
$ok = true;
foreach ($manifest as $k => [$w, $t]) {
    $pass = $w === $t;
    $ok = $ok && $pass;
    printf("  %-14s written=%-8d graph=%-8d %s\n", $k, $w, $t, $pass ? 'PASS' : '**FAIL**');
}
echo $ok ? "\nALL COUNTS MATCH\n" : "\nMISMATCH — DO NOT PROCEED\n";
