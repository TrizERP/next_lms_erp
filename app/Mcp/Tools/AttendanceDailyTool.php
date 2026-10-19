<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;

class AttendanceDailyTool extends AbstractMcpTool
{
    public function __construct(private readonly AttendanceInsightService $service)
    {
    }

    public function name(): string
    {
        return 'attendance.daily';
    }

    public function description(): string
    {
        return 'The attendance register for ONE day: how many students were present, absent or otherwise '
            .'marked, and the students under one status (absent by default), for the school, a class or a '
            .'division. Defaults to today. When nothing is recorded for the day it says so and names the '
            .'latest day that has records, instead of reporting that nobody was absent. Use it for '
            .'"who is absent today?", "how many were present on <date>?" and "absent students in Class 6". '
            .'For a rate over a period use attendance.overview. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, or "today" or "yesterday". Defaults to today.'],
                'status' => ['type' => 'string', 'enum' => ['absent', 'present', 'other'], 'default' => 'absent'],
                'standard' => ['type' => 'string', 'description' => 'A class named in words, for example "Class 6" or "6".'],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'student_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer', 'minimum' => 1],
                    'description' => 'Only these students.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'attendance.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->daily($context, $arguments);
    }
}
