<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\NewPalReportService;

class NewPalGamificationSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly NewPalReportService $service)
    {
    }

    public function name(): string
    {
        return 'new_pal.gamification_summary';
    }

    public function description(): string
    {
        return 'Recorded learning events, streaks, badges and framework progress for one learner, '
            . 'from New PAL\'s own gamification records. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->gamificationSummary($context, $arguments);
    }
}
