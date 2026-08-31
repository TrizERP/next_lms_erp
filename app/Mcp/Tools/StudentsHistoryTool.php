<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PeopleDirectoryService;

class StudentsHistoryTool extends AbstractMcpTool
{
    public function __construct(private readonly PeopleDirectoryService $service)
    {
    }

    protected function name(): string
    {
        return 'students.history';
    }

    protected function description(): string
    {
        return 'One student\'s enrolment history across academic years — which class they were in '
            . 'each year, how long they have been at the school, and whether a year appears twice. '
            . 'Every other student tool is scoped to the current year; this is the only one that '
            . 'looks back.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['student_id'],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->history($context, $arguments);
    }
}
