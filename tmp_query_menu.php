<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = DB::table('tblmenumaster')
    ->where('status', 1)
    ->where('parent_menu_id', 429)
    ->orderBy('level')
    ->orderBy('sort_order')
    ->get(['id', 'name', 'link', 'parent_menu_id', 'level', 'sort_order', 'menu_path']);

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
