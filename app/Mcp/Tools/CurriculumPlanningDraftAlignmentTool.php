<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;

class CurriculumPlanningDraftAlignmentTool extends AbstractMcpTool
{
    public function __construct(private readonly CurriculumPlanningReportService $service)
    {
    }

    public function name(): string
    {
        return 'curriculum_planning.draft_alignment_note';
    }

    public function description(): string
    {
        return 'Drafts non-dispatched, evidence-backed curriculum pacing adjustments and competency alignment proposals for educator approval. Sends nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_name' => ['type' => 'string'],
                'curricula_gaps' => ['type' => 'array'],
                'pacing_gaps' => ['type' => 'array'],
                'outcomes_unmapped' => ['type' => 'array'],
            ],
            'additionalProperties' => true,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'write', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->draftAlignmentNote($context, $arguments);
    }
}

