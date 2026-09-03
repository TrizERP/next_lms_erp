<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;

class AttendanceStudentTool extends AbstractMcpTool
{
    public function __construct(private readonly AttendanceInsightService $service)
    {
    }

    protected function name(): string
    {
        return 'attendance.student';
    }

    protected function description(): string
    {
        return 'One student\'s attendance over a recent window: present and absent day counts, '
            . 'the rate, and the dates they were absent. Returns no rate when too few days are '
            . 'coded to state one honestly.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30],
            ],
            'required' => ['student_id'],
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

        return $this->service->forStudent($context, $arguments);
    }
}
