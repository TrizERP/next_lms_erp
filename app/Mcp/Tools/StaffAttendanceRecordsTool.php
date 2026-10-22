<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\StaffAttendanceService;
use App\Services\Mcp\McpRequestContext;

/**
 * Staff punch-in and punch-out records for this institute within a date window (default last 30 days). Staff only. Records no shift or leave link, so it cannot say who was late or absent.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class StaffAttendanceRecordsTool extends AbstractMcpTool
{
    public function __construct(private readonly StaffAttendanceService $service)
    {
    }

    public function name(): string
    {
        return 'staff_attendance.records';
    }

    public function description(): string
    {
        return 'Staff punch-in and punch-out records for this institute within a date window (default last 30 days). Staff only. Records no shift or leave link, so it cannot say who was late or absent. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'staff_id' => ['type' => 'integer', 'minimum' => 1],
                'only_without_punch_out' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->records($context, $arguments);
    }
}
