<?php
// PHASE 1 — read-only schema inventory of the authoritative MariaDB.
// Writes JSON to scratchpad. NEVER writes to MariaDB or Neo4j.
$env = [];
foreach (file(dirname(dirname(__DIR__)) . '/.env') as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE']),
    $env['DB_USERNAME'], $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$db = $env['DB_DATABASE'];

// 1. base table metadata
$tables = [];
$q = $pdo->query("SELECT table_name, table_type, engine, table_rows,
        ROUND((data_length+index_length)/1024/1024, 2) AS size_mb
    FROM information_schema.tables WHERE table_schema = DATABASE()
    ORDER BY table_name");
foreach ($q as $r) {
    $tables[$r['table_name']] = [
        'table'      => $r['table_name'],
        'type'       => $r['table_type'],
        'engine'     => $r['engine'],
        'est_rows'   => (int)$r['table_rows'],
        'size_mb'    => (float)$r['size_mb'],
        'exact_rows' => null,
        'pk'         => [],
        'columns'    => [],
    ];
}
fwrite(STDERR, "meta: " . count($tables) . " tables\n");

// 2. all columns in one pass
$q = $pdo->query("SELECT table_name, column_name, column_key, data_type
    FROM information_schema.columns WHERE table_schema = DATABASE()
    ORDER BY table_name, ordinal_position");
foreach ($q as $r) {
    $t = $r['table_name'];
    if (!isset($tables[$t])) continue;
    $tables[$t]['columns'][$r['column_name']] = $r['data_type'];
    if ($r['column_key'] === 'PRI') $tables[$t]['pk'][] = $r['column_name'];
}
fwrite(STDERR, "columns done\n");

// 3. exact row counts, one table at a time
$i = 0;
foreach ($tables as $name => &$t) {
    $i++;
    if ($t['type'] !== 'BASE TABLE') { $t['exact_rows'] = -1; continue; }
    try {
        $t['exact_rows'] = (int)$pdo->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();
    } catch (Throwable $e) {
        $t['exact_rows'] = -2;
        $t['count_error'] = substr($e->getMessage(), 0, 200);
    }
    if ($i % 50 === 0) fwrite(STDERR, "counted $i/" . count($tables) . "\n");
}
unset($t);

file_put_contents(
    __DIR__ . '/inventory.json',
    json_encode(array_values($tables), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
$total = array_sum(array_map(fn($t) => max(0, $t['exact_rows']), $tables));
echo "DONE. tables=" . count($tables) . " exact_total_rows=$total\n";
