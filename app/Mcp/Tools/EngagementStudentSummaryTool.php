<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;

class EngagementStudentSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly EngagementReportService $service)
    {
    }

    public function name(): string
    {
        return 'engagement.student_summary';
    }

    public function description(): string
    {
        return 'A student\'s attendance rate, homework completion and assignment completion over a '
            . 'period, computed live from the attendance, homework and LMS assignment records. Nothing '
            . 'is stored — a missing signal is reported as no data, never as zero. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'days_back' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 180, 'default' => 30],
            ],
            'required' => ['student_id'],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->studentSummary($context, $arguments);
    }
}
