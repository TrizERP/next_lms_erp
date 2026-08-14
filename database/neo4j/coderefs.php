<?php
// PHASE 1 — count code references per table name across the repo.
// Read-only. Counts "quoted" references (the meaningful kind: DB::table('x'),
// 'x' in raw SQL, ->from('x')) separately from bare word-boundary hits, because
// table names like `batch`, `subject`, `standard`, `timetable` are English words.
$root = 'c:/xampp/htdocs/next_lms_erp';
$inv = json_decode(file_get_contents(__DIR__ . '/inventory.json'), true);
$names = array_column($inv, 'table');

$scanDirs = ['app', 'routes', 'resources/views', 'database', 'config', 'public'];
$exts = ['php', 'blade.php', 'sql', 'js', 'json'];

$files = [];
foreach ($scanDirs as $d) {
    $path = "$root/$d";
    if (!is_dir($path)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $p = str_replace('\\', '/', $f->getPathname());
        if (str_contains($p, '/vendor/') || str_contains($p, '/node_modules/')) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['php', 'sql', 'js', 'json'], true)) continue;
        if ($f->getSize() > 4_000_000) continue;
        $files[] = $p;
    }
}
fwrite(STDERR, "scanning " . count($files) . " files\n");

$quoted = array_fill_keys($names, 0);
$bare   = array_fill_keys($names, 0);
$where  = array_fill_keys($names, []);

// longest names first so `fees_breackoff_logs` isn't eaten by `fees_breackoff`
usort($names, fn($a, $b) => strlen($b) <=> strlen($a));

$n = 0;
foreach ($files as $p) {
    $src = @file_get_contents($p);
    if ($src === false) continue;
    $rel = substr($p, strlen($root) + 1);
    $n++;
    if ($n % 500 === 0) fwrite(STDERR, "  $n files\n");
    foreach ($names as $t) {
        if (stripos($src, $t) === false) continue;
        // quoted / backticked / SQL-positional reference
        $qc = preg_match_all('/(?:[\'"`]' . preg_quote($t, '/') . '[\'"`])|(?:\b(?:from|join|into|update|table)\s+`?' . preg_quote($t, '/') . '`?\b)/i', $src);
        // bare word-boundary reference
        $bc = preg_match_all('/\b' . preg_quote($t, '/') . '\b/i', $src);
        if ($qc) { $quoted[$t] += $qc; if (count($where[$t]) < 6) $where[$t][] = $rel; }
        if ($bc) { $bare[$t] += $bc; }
    }
}

$out = [];
foreach ($inv as $t) {
    $out[$t['table']] = [
        'quoted' => $quoted[$t['table']] ?? 0,
        'bare'   => $bare[$t['table']] ?? 0,
        'files'  => $where[$t['table']] ?? [],
    ];
}
file_put_contents(__DIR__ . '/coderefs.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "DONE files=" . count($files) . "\n";
