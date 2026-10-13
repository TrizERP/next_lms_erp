<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\DonationService;
use App\Services\Mcp\McpRequestContext;

/**
 * Donations received by the school with donor name, head, amount, payment mode and receipt number. Donor PAN, address, phone and bank details are never returned.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class DonationsListTool extends AbstractMcpTool
{
    public function __construct(private readonly DonationService $service)
    {
    }

    public function name(): string
    {
        return 'donations.list';
    }

    public function description(): string
    {
        return 'Donations received by the school with donor name, head, amount, payment mode and receipt number. Donor PAN, address, phone and bank details are never returned. Changes nothing.';
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
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->collections($context, $arguments);
    }
}
