<?php
use App\Http\Controllers\AI\WorkspaceController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

// Simulate the generate request
$request = Request::create('/api/ai/workspace/generate', 'POST', [
    'route' => '/fees',
    'template_key' => 'k12.fees.pending_summary',
    'variables' => [],
]);

$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(WorkspaceController::class)->generate($request)->getContent(), true);

// Check the variables that were passed
echo "Variables passed to template:\n";
$context = app(\App\Domain\AI\Workspace\AiContextService::class)->resolve('/fees', $scope, []);
echo "Page hasRecords: " . ($context->page->hasRecords() ? 'true' : 'false') . "\n";
echo "Page recordCount: " . $context->page->recordCount . "\n";
echo "Page records count: " . count($context->page->records) . "\n";
echo "Page metrics count: " . count($context->page->metrics) . "\n";

$fallback = app(\App\Domain\AI\Workspace\PageDataResolver::class)->resolve($context);
echo "\nFallback resolved: " . ($fallback['resolved'] ? 'true' : 'false') . "\n";
echo "Fallback source: " . ($fallback['source'] ?? 'none') . "\n";
echo "Fallback records count: " . count($fallback['records']) . "\n";
echo "Fallback record_count: " . $fallback['record_count'] . "\n";
echo "Fallback metrics count: " . count($fallback['metrics']) . "\n";

$variables = app(WorkspaceController::class)->pageVariables($context);
echo "\nFinal variables:\n";
echo "records: " . mb_substr($variables['records'], 0, 200) . "\n";
echo "metrics: " . mb_substr($variables['metrics'], 0, 200) . "\n";