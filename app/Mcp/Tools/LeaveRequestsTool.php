<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StaffLeaveService;

/**
 * Staff leave requests with their status and the dates requested.
 *
 * Read-only by construction, and no argument can widen it: the institute comes from the
 * caller's token inside the service.
 */
class LeaveRequestsTool extends AbstractMcpTool
{
    public function __construct(private readonly StaffLeaveService $service)
    {
    }

    public function name(): string
    {
        return 'leave.requests';
    }

    public function description(): string
    {
        return 'Staff leave requests for this institute, with the staff member, department, leave type, '
            .'dates requested and status (pending, approved, approved_lwp, rejected, cancelled). Staff '
            .'leave only: students\' leave applications are a different module. Reports dates and status; '
            .'does NOT compute leave balances or days taken. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['any', 'pending', 'approved', 'approved_lwp', 'rejected', 'cancelled'], 'default' => 'any'],
                'staff_id' => ['type' => 'integer', 'minimum' => 1],
                'department_id' => ['type' => 'integer', 'minimum' => 1],
                'leave_type_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD; requests ending on or after this day.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD; requests starting on or before this day.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'leave.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->requests($context, $arguments);
    }
}
