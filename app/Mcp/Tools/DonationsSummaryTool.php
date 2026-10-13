<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\DonationService;
use App\Services\Mcp\McpRequestContext;

/**
 * Donations counted and totalled by head and by payment mode for this institute, with the number of distinct donors.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class DonationsSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly DonationService $service)
    {
    }

    public function name(): string
    {
        return 'donations.summary';
    }

    public function description(): string
    {
        return 'Donations counted and totalled by head and by payment mode for this institute, with the number of distinct donors. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'donation_head' => ['type' => 'string'],
                'payment_mode' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'donations.read'];
    }

    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        return ['admin'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
