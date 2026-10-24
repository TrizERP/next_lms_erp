<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $policy = app(\App\Services\PAL\EsoPolicyService::class);
    $res = $policy->studentDashboard(97382, 1, '2022');
    echo "RESULT:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
