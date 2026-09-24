<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;

class CurriculumPlanningStatusTool extends AbstractMcpTool
{
    public function __construct(private readonly CurriculumPlanningReportService $service)
    {
    }

    public function name(): string
    {
        return 'curriculum_planning.status';
    }

    public function description(): string
    {
        return 'Curriculum -> unit -> chapter coverage for this institute, filtered by standard or subject. '
            . 'Reports how many chapters are configured and how many are completed. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'syear' => ['type' => 'integer', 'minimum' => 2000],
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

        return $this->service->status($context, $arguments);
    }
}
