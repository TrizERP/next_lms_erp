<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\EngagementReportService;
use App\Services\Mcp\McpRequestContext;

class EngagementDraftInterventionTool extends AbstractMcpTool
{
    public function __construct(private readonly EngagementReportService $service)
    {
    }

    public function name(): string
    {
        return 'engagement.draft_intervention_note';
    }

    public function description(): string
    {
        return 'Drafts a factual, objective, non-judgmental re-engagement intervention note for a student based '
            . 'on their verified attendance, homework, and coursework activity. Drafts are queued for human educator '
            . 'approval only. Changes no student marks, sends no automated messages.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'student_name' => ['type' => 'string'],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'deficiency_reasons' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'attendance_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                'homework_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                'assignments_percent' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                'unanswered_doubts_count' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['student_id'],
            'additionalProperties' => true,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'draft', 'required_permission' => 'student.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->draftIntervention($context, $arguments);
    }
}

