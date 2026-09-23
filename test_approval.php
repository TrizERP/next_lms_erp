<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

// First ask the analysis question to get a recommendation
$request = Request::create('/api/ai/ask', 'POST', [
    'question' => 'Analyze the students with pending fees and identify the highest payment risk',
    'module' => 'fees', 'route' => '/fees',
    'limit' => 5,
]);
$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

echo "=== First question - Analysis ===\n";
echo "Answer headline: " . ($d['answer']['headline'] ?? 'none') . "\n";
echo "Recommendation ID: " . (($d['answer']['actions'][0]['payload']['recommendation_id'] ?? 'none')) . "\n\n";

$recommendationId = $d['answer']['actions'][0]['payload']['recommendation_id'];

// Now follow up with approval
$request2 = Request::create('/api/ai/ask', 'POST', [
    'question' => 'Approve the recommendation.',
    'module' => 'fees', 'route' => '/fees',
    'conversation_id' => $d['conversation']['id'] ?? 0,
]);
$request2->attributes->set('mcp_context', $scope);

$d2 = json_decode(app(AskController::class)->ask($request2)->getContent(), true)['data'] ?? [];

echo "=== Follow-up - Approve ===\n";
foreach ($d2['ladder'] ?? [] as $line) echo "$line\n";
echo "\nAnswer: " . mb_substr(is_string($d2['answer'] ?? '') ? $d2['answer'] : json_encode($d2['answer']), 0, 500) . "\n";