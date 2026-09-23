<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;

class CurriculumPlanningOutcomesTool extends AbstractMcpTool
{
    public function __construct(private readonly CurriculumPlanningReportService $service)
    {
    }

    public function name(): string
    {
        return 'curriculum_planning.outcomes';
    }

    public function description(): string
    {
        return 'The learning outcomes and competencies declared against one curriculum. Use '
            . 'curriculum_planning.status first to resolve a curriculum_id. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'curriculum_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->outcomes($context, $arguments);
    }
}
