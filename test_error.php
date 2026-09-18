<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$request = Request::create('/api/ai/ask', 'POST', [
    'question' => 'Analyze the students with pending fees and identify the highest payment risk',
    'module' => 'fees', 'route' => '/fees',
    'limit' => 5,
]);
$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

echo "Agent trace:\n";
foreach ($d['trace'] ?? [] as $t) {
    if (is_array($t) && ($t['key'] ?? '') === 'agent') {
        echo json_encode($t, JSON_PRETTY_PRINT) . "\n";
    }
}