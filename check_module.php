<?php
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
$m = app(ModuleRegistry::class)->find('fees', 1);
echo 'mcpTools: ' . implode(', ', $m->mcpTools) . "\n";
echo 'capabilities: ' . json_encode($m->capabilities) . "\n";