<?php
use App\Http\Controllers\AI\WorkspaceController;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$templates = [
    'k12.fees.pending_summary' => 'Pending fees summary',
    'k12.fees.collection_report' => 'Summarise fee collection',
    'k12.fees.defaulter_report' => 'Analyse fee defaulters',
    'k12.fees.pending_report' => 'Pending fees report',
];

foreach ($templates as $key => $label) {
    echo "\n=== $label ($key) ===\n";
    $request = Request::create('/api/ai/workspace/generate', 'POST', [
        'route' => '/fees',
        'template_key' => $key,
        'variables' => [],
    ]);
    $request->attributes->set('mcp_context', $scope);

    $d = json_decode(app(WorkspaceController::class)->generate($request)->getContent(), true);

    if ($d['success']) {
        echo "SUCCESS\n";
        echo mb_substr($d['data']['content'] ?? '', 0, 300) . "\n";
    } else {
        echo "FAILED: " . ($d['message'] ?? 'unknown') . "\n";
    }
}