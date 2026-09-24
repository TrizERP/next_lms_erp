<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\OnlineExamReportService;

class OnlineExamSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly OnlineExamReportService $service)
    {
    }

    public function name(): string
    {
        return 'exam_assessment.online_exam_summary';
    }

    public function description(): string
    {
        return 'Online exam performance — exams published, attempts recorded, average score and the attempts '
            . 'below the passing bar (40% by default, the same bar the LMS Result Dashboard uses), filtered by '
            . 'standard, subject or student. PAL-generated papers are excluded; only teacher-published exams are counted.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'at_risk_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100, 'default' => 40],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'exam_assessment.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
