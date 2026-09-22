<?php
use App\Http\Controllers\AI\WorkspaceController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
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

echo "Response:\n";
echo json_encode($d, JSON_PRETTY_PRINT);