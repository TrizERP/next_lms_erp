<?php
require __DIR__ . '/../next_lms_erp/vendor/autoload.php';
$app = require_once __DIR__ . '/../next_lms_erp/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$resolver = app(App\Domain\AI\Lifecycle\Modules\ModuleResolver::class);
$result = $resolver->resolve('Which students have pending fees?', ['route' => '/chatbot'], 0);
echo "Module key: " . $result['module']->key . PHP_EOL;
echo "Source: " . $result['source'] . PHP_EOL;
echo "mcpTools count: " . count($result['module']->mcpTools) . PHP_EOL;
echo "Scores: " . PHP_EOL;
print_r($result['scores'] ?? $result['considered'] ?? []);
