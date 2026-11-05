<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = DB::select("SHOW TABLES LIKE 'ai_%'");
foreach ($tables as $t) {
    print_r(array_values((array)$t)[0] . "\n");
}
