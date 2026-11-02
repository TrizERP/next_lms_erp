<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$nodeToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwic3ViX2luc3RpdHV0ZV9pZCI6IjEsMCIsImlzX2FkbWluIjoyLCJjbGllbnRfaWQiOjEsInVzZXJfcHJvZmlsZV9pZCI6MSwiaXNfc3R1ZGVudCI6ZmFsc2UsImlhdCI6MTc5MzYxNDM0NCwiZXhwIjoxNzk2MjA2MzQ0fQ.C7_8LS1-TdO4SaqtQBps-A04_q7J6imO196xpLQZq3g';

try {
    $decoded = Firebase\JWT\JWT::decode($nodeToken, new Firebase\JWT\Key('b6bpRQ92jy9ty16FilMW54dsH9le3KhE', 'HS256'));
    echo 'DECODE SUCCESS: ' . json_encode($decoded) . PHP_EOL;
} catch (Exception $e) {
    echo 'DECODE FAILED: ' . $e->getMessage() . PHP_EOL;
}

$jwt = app(GenTux\Jwt\JwtToken::class);
$jwt->setToken($nodeToken);
echo 'GENTUX VALID: ' . ($jwt->validate() ? 'YES' : 'NO') . PHP_EOL;

