<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ConsentService;
use App\Services\Mcp\McpRequestContext;

/**
 * Consents counted by decision state and by accountability.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ConsentSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly ConsentService $service)
    {
    }

    public function name(): string
    {
        return 'consent.summary';
    }

    public function description(): string
    {
        return 'Consents for this institute counted by decision state and by accountability, over the whole '
            .'filtered set. Consents with no decision recorded are counted under their own name and are never '
            .'folded into a refusal. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'decision' => ['type' => 'string', 'enum' => ['any', 'awaiting', 'recorded'], 'default' => 'any'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'consent.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
