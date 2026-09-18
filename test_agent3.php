<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$request = Request::create('/api/ai/ask', 'POST', [
    'question' => 'Analyze the students with pending fees and identify the highest payment risk',
    'module' => 'fees', 'route' => '/fees',
]);
$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

echo "Answer: ";
var_dump($d['answer'] ?? 'no answer');

echo "\n\nAgent error details:\n";
if (isset($d['agent_error'])) {
    var_dump($d['agent_error']);
}

echo "\n\nTrace:\n";
foreach ($d['trace'] ?? [] as $t) {
    if (is_array($t)) {
        echo json_encode($t) . "\n";
    }
}