<?php
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\FeesCollectionReportService;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$service = app(FeesCollectionReportService::class);
$result = $service->report($scope, []);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);