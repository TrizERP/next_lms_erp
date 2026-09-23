<?php
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\FeesArrearsService;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 61, allowedInstituteIds: [61],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$service = app(FeesArrearsService::class);
$result = $service->arrears($scope, []);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);