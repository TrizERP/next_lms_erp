<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\NewPalReportService;

class NewPalDraftAdaptiveInterventionTool extends AbstractMcpTool
{
    public function __construct(private readonly NewPalReportService $service)
    {
    }

    public function name(): string
    {
        return 'new_pal.draft_adaptive_intervention';
    }

    public function description(): string
    {
        return 'Drafts non-dispatched, personalized adaptive learning interventions for learners stuck at foundational mastery tiers. Sends nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'flagged_learners' => ['type' => 'array'],
                'coherence_summary' => ['type' => 'object'],
            ],
            'additionalProperties' => true,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'write', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->draftAdaptiveIntervention($context, $arguments);
    }
}

