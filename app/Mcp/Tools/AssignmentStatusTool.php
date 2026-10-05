<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AssignmentReportService;
use App\Services\Mcp\McpRequestContext;

class AssignmentStatusTool extends AbstractMcpTool
{
    public function __construct(private readonly AssignmentReportService $service)
    {
    }

    public function name(): string
    {
        return 'exam_assessment.assignment_status';
    }

    public function description(): string
    {
        return 'Assignment, Worksheet and Project status — counts submitted, reviewed and not-submitted, plus '
            . 'the not-submitted list, filtered by standard, division, subject, student or work type. Worksheet '
            . 'and Project are the same records as Assignment, distinguished only by work_type.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'work_type' => ['type' => 'string', 'enum' => ['assignment', 'worksheet', 'project']],
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

        return $this->service->status($context, $arguments);
    }
}
