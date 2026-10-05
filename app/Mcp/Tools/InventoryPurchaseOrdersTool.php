<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InventoryService;
use App\Services\Mcp\McpRequestContext;

/**
 * Purchase order lines with the item, vendor name, quantity and amount.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class InventoryPurchaseOrdersTool extends AbstractMcpTool
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function name(): string
    {
        return 'inventory.purchase_orders';
    }

    public function description(): string
    {
        return 'Purchase order lines raised by this institute, with the item, the vendor\'s NAME, the quantity, '
            .'the amount and the approval status. A purchase order is an order and not a delivery. The '
            .'vendor\'s bank, PAN and registration details are held on the vendor record and are NOT readable '
            .'through this tool. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer', 'minimum' => 1],
                'vendor_id' => ['type' => 'integer', 'minimum' => 1],
                'po_number' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'inventory.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->purchaseOrders($context, $arguments);
    }
}
