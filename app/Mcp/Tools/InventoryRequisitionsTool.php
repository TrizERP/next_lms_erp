<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InventoryService;
use App\Services\Mcp\McpRequestContext;

/**
 * Requisitions raised against inventory items, with what was requested and what was approved.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class InventoryRequisitionsTool extends AbstractMcpTool
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function name(): string
    {
        return 'inventory.requisitions';
    }

    public function description(): string
    {
        return 'Requisitions raised against this institute\'s inventory, with the item, the quantity requested '
            .'and approved, who raised it, who approved it and when. A requisition is a request: nothing '
            .'records that the item was then handed over, so an approved requisition is not proof of an issue. '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer', 'minimum' => 1],
                'department_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'description' => 'Matches the requisition status title.'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
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

        return $this->service->requisitions($context, $arguments);
    }
}
