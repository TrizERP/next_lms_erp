<?php
use App\Domain\AI\Workspace\ModuleToolData;
use App\Domain\AI\Workspace\AiContext;
use App\Domain\AI\Workspace\PageSnapshot;
use App\Services\Mcp\McpRequestContext;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

// Build a minimal context similar to what WorkspaceController would pass
$page = new PageSnapshot(
    title: 'Fees',
    records: [],
    metrics: [],
    recordCount: 0,
);

$context = new AiContext(
    scope: $scope,
    route: '/fees',
    moduleKey: 'fees',
    moduleLabel: 'Fees',
    page: $page,
    pageType: 'list',
);

$moduleTools = app(ModuleToolData::class);
$result = $moduleTools->resolve($context);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);