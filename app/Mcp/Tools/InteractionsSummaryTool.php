<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InteractionReportService;
use App\Services\Mcp\McpRequestContext;

class InteractionsSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly InteractionReportService $service)
    {
    }

    public function name(): string
    {
        return 'interactions.summary';
    }

    public function description(): string
    {
        return 'Interaction counts by type over a window, and which follow-ups are still open. '
            . 'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days_back' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 180, 'default' => 30],
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

        return $this->service->summary($context, $arguments);
    }
}
