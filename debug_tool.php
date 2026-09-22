<?php
use App\Services\Mcp\McpRequestContext;
use App\Mcp\ToolRegistry;

$scope = new McpRequestContext(
    userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
    userProfileId: null, clientId: null, academicYear: 2026, termId: null,
    isAdmin: true, isStudent: false,
);

$registry = app(ToolRegistry::class);
$tool = $registry->tool('fees.arrears');

if ($tool === null) {
    echo "Tool not found\n";
    exit;
}

echo "Tool found: " . $tool->name() . "\n";

try {
    $result = $tool->execute([], $scope);
    echo "Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}