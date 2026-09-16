<?php
require __DIR__ . '/../next_lms_erp/vendor/autoload.php';
$app = require_once __DIR__ . '/../next_lms_erp/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$registry = app(App\Mcp\ToolRegistry::class);
$defs = $registry->definitions();
$feesTools = ['fees.getPending', 'fees.arrears', 'fees.collection_report'];

foreach ($defs as $def) {
    if (in_array($def['name'], $feesTools)) {
        echo "Tool: " . $def['name'] . PHP_EOL;
        echo "Description: " . $def['description'] . PHP_EOL;
        echo "Annotations: " . json_encode($def['annotations'] ?? []) . PHP_EOL;
        echo PHP_EOL;
    }
}
