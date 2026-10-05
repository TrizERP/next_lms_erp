<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;

class EngagementStudentsNeedingAttentionTool extends AbstractMcpTool
{
    public function __construct(private readonly EngagementReportService $service)
    {
    }

    public function name(): string
    {
        return 'engagement.students_needing_attention';
    }

    public function description(): string
    {
        return 'Students whose computed attendance, homework or assignment completion falls below a '
            . 'threshold, for a class or the whole institute. The threshold is a filter applied at read '
            . 'time, not a stored score. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'threshold_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100, 'default' => 60],
                'days_back' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 180, 'default' => 30],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
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

        return $this->service->studentsNeedingAttention($context, $arguments);
    }
}
