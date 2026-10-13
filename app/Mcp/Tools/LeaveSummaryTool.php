<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\StaffLeaveService;

/**
 * Staff leave counted by status, leave type and department.
 *
 * Read-only by construction; the institute comes from the caller's token.
 */
class LeaveSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly StaffLeaveService $service)
    {
    }

    public function name(): string
    {
        return 'leave.summary';
    }

    public function description(): string
    {
        return 'Staff leave requests counted by status, leave type and department for this institute. '
            .'Staff leave only. Does NOT compute leave balances or days taken. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['any', 'pending', 'approved', 'approved_lwp', 'rejected', 'cancelled'], 'default' => 'any'],
                'department_id' => ['type' => 'integer', 'minimum' => 1],
                'leave_type_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD; requests ending on or after this day.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD; requests starting on or before this day.'],
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

        return $this->service->summary($context, $arguments);
    }
}
