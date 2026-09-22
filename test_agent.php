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
    'question' => 'Which students currently have pending or unpaid fees?',
    'module' => 'fees', 'route' => '/fees',
]);
$request->attributes->set('mcp_context', $scope);

$d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

echo "=== Ladder ===\n";
foreach ($d['ladder'] ?? [] as $line) echo "$line\n";

echo "\n=== Stage counts ===\n";
echo json_encode($d['stage_counts'] ?? []) . "\n";

echo "\n=== Depth reached ===\n";
echo ($d['depth_reached'] ?? '?') . "\n";

echo "\n=== Answer source ===\n";
echo ($d['answer_source'] ?? '?') . "\n";

echo "\n=== Answer ===\n";
echo ($d['answer'] ?? 'no answer') . "\n";