<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PettyCashService;

/**
 * Petty cash spending totalled by head and by month.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class PettyCashSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly PettyCashService $service)
    {
    }

    public function name(): string
    {
        return 'petty_cash.summary';
    }

    public function description(): string
    {
        return 'Petty cash spending for this institute totalled by head and by month over the whole filtered '
            .'set. `total_amount` is money recorded as going OUT; this book records no float, top-up or '
            .'reimbursement, so it is not a balance and no remaining figure can be derived from it. Changes '
            .'nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'min_amount' => ['type' => 'number', 'minimum' => 0],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'petty_cash.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
