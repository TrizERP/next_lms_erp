<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ResultReportService;

class ExamsResultsTool extends AbstractMcpTool
{
    public function __construct(private readonly ResultReportService $service)
    {
    }

    protected function name(): string
    {
        return 'exams.results';
    }

    protected function description(): string
    {
        return 'Recorded exam marks, filtered by student, exam, subject or class. Returns the '
            . 'average across scored entries; absences carry no score and are counted separately '
            . 'rather than averaged in as zeros.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'exam_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_name' => ['type' => 'string'],
                'standard_name' => ['type' => 'string'],
                'exam_title' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 300, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'result.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->report($context, $arguments);
    }
}
