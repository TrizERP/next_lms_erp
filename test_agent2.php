<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$questions = [
    'Analyze the students with pending fees and identify the highest payment risk',
    'Review fee defaulters and prepare a parent follow-up',
    'Which students have outstanding fees and what action should we take?',
];

foreach ($questions as $i => $q) {
    echo "\n========== QUESTION " . ($i+1) . " ==========\n";
    echo "$q\n\n";
    
    $request = Request::create('/api/ai/ask', 'POST', [
        'question' => $q,
        'module' => 'fees', 'route' => '/fees',
    ]);
    $request->attributes->set('mcp_context', $scope);

    $d = json_decode(app(AskController::class)->ask($request)->getContent(), true)['data'] ?? [];

    foreach ($d['ladder'] ?? [] as $line) echo "$line\n";
    echo "\nStage counts: " . json_encode($d['stage_counts'] ?? []) . "\n";
    echo "Depth reached: " . ($d['depth_reached'] ?? '?') . "\n";
    echo "Answer source: " . ($d['answer_source'] ?? '?') . "\n";
    echo "Answer: " . mb_substr($d['answer'] ?? 'no answer', 0, 300) . "\n";
}