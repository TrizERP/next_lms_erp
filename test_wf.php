<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

// Check workflow status
$request = Request::create('/api/ai/workflow-status', 'GET', [
    'module' => 'fees', 'workflow' => 'fees_collection',
]);
$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

echo "Workflow status:\n";
var_dump($d);