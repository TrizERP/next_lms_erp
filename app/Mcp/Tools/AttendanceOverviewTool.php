<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;

class AttendanceOverviewTool extends AbstractMcpTool
{
    public function __construct(private readonly AttendanceInsightService $service)
    {
    }

    protected function name(): string
    {
        return 'attendance.overview';
    }

    protected function description(): string
    {
        return 'Attendance rates across a cohort over a recent window, worst first. Answers '
            . '"who has low attendance?" and "how is 8B attending?". Students with too few coded '
            . 'days to judge are counted separately rather than shown as zero.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'attendance.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->overview($context, $arguments);
    }
}
