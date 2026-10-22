<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\StaffAttendanceService;
use App\Services\Mcp\McpRequestContext;

/**
 * Staff punches per day for this institute: how many people punched in and how many have no punch-out recorded, within a date window (default last 30 days). Staff only.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class StaffAttendanceSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly StaffAttendanceService $service)
    {
    }

    public function name(): string
    {
        return 'staff_attendance.summary';
    }

    public function description(): string
    {
        return 'Staff punches per day for this institute: how many people punched in and how many have no punch-out recorded, within a date window (default last 30 days). Staff only. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'staff_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'staff_attendance.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
