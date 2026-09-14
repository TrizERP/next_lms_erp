<?php
require __DIR__ . '/../next_lms_erp/vendor/autoload.php';
$app = require_once __DIR__ . '/../next_lms_erp/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$resolver = app(App\Domain\AI\Configuration\AiConfigurationResolver::class);
$config = $resolver->resolve('agent_reasoning', 0);
echo "Config: " . json_encode($config) . PHP_EOL;

$factory = app(App\Domain\AI\Configuration\AiModelClientFactory::class);
$client = $factory->for('agent_reasoning', 0);
echo "isConfigured: " . ($client->isConfigured() ? 'true' : 'false') . PHP_EOL;
