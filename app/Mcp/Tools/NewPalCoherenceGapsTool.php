<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\NewPalReportService;

class NewPalCoherenceGapsTool extends AbstractMcpTool
{
    public function __construct(private readonly NewPalReportService $service)
    {
    }

    public function name(): string
    {
        return 'new_pal.coherence_gaps';
    }

    public function description(): string
    {
        return 'Concept relations and mastery evidence for one class and subject from the coherence '
            . 'map: concepts without content, without questions, cycles and isolated nodes. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'subject_id' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'required' => ['standard_id', 'subject_id'],
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

        return $this->service->coherenceGaps($context, $arguments);
    }
}
