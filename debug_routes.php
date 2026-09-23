<?php
use App\Domain\AI\Workspace\AiContextService;
use App\Services\Mcp\McpRequestContext;
use App\Domain\AI\Workspace\PageDataResolver;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

// Test different routes
$routes = ['/fees', '/fees/collect', '/fees/collect/123', '/fees/dashboard', '/fees/reports'];

foreach ($routes as $route) {
    echo "\n=== Route: $route ===\n";
    $context = app(AiContextService::class)->resolve($route, $scope, []);
    echo "Module: " . ($context->moduleKey ?? 'none') . "\n";
    echo "Entity: " . ($context->entityKey ?? 'none') . " " . ($context->entityId ?? '') . "\n";
    echo "Page type: " . $context->pageType . "\n";
    echo "Page hasRecords: " . ($context->page->hasRecords() ? 'true' : 'false') . "\n";
    
    $fallback = app(PageDataResolver::class)->resolve($context);
    echo "Fallback resolved: " . ($fallback['resolved'] ? 'true' : 'false') . "\n";
    echo "Fallback source: " . ($fallback['source'] ?? 'none') . "\n";
    echo "Fallback records: " . count($fallback['records']) . "\n";
    echo "Fallback record_count: " . $fallback['record_count'] . "\n";
    echo "Fallback metrics: " . count($fallback['metrics']) . "\n";
}