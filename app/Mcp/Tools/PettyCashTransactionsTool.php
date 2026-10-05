<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PettyCashService;

/**
 * Petty cash spends, with the head, amount, date, who entered it and whether a bill is attached.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class PettyCashTransactionsTool extends AbstractMcpTool
{
    public function __construct(private readonly PettyCashService $service)
    {
    }

    public function name(): string
    {
        return 'petty_cash.transactions';
    }

    public function description(): string
    {
        return 'Petty cash spends for this institute, newest first, with the head, description, amount, date, '
            .'the user who entered it and whether a scan of the bill is on file. The total is summed over '
            .'every matching transaction, not just the rows listed. This book records NO approval of any kind '
            .'and NO opening float or top-up, so nothing is pending approval and no balance can be stated. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'A petty cash head id.'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'min_amount' => ['type' => 'number', 'minimum' => 0],
                'without_bill_only' => ['type' => 'boolean', 'description' => 'Only spends with no bill scan on file.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->transactions($context, $arguments);
    }
}
