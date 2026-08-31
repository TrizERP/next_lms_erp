<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\HomeworkInsightService;
use App\Services\Mcp\McpRequestContext;

class HomeworkListTool extends AbstractMcpTool
{
    public function __construct(private readonly HomeworkInsightService $service)
    {
    }

    protected function name(): string
    {
        return 'homework.list';
    }

    protected function description(): string
    {
        return 'Homework assigned over a recent window, with whether each item has been handed in. '
            . 'Filter by student, subject, class or status. Only work whose due date has passed is '
            . 'reported as overdue.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => [
                    'type' => 'string',
                    'enum' => ['overdue', 'pending', 'submitted'],
                    'description' => 'Narrow to one state. Omit for everything.',
                ],
                'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->list($context, $arguments);
    }
}
