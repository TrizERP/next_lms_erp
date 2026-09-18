<?php
use App\Http\Controllers\AI\AskController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$questions = [
    'Which students currently have pending or unpaid fees?',
    'Analyze the students with pending fees and identify the highest payment risk',
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
    echo "Answer: " . mb_substr(is_string($d['answer'] ?? '') ? $d['answer'] : json_encode($d['answer']), 0, 500) . "\n";
}