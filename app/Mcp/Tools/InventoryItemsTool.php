<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\InventoryService;
use App\Services\Mcp\McpRequestContext;

/**
 * Items on the item master, with the stock figure recorded against each and its reorder level.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class InventoryItemsTool extends AbstractMcpTool
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function name(): string
    {
        return 'inventory.items';
    }

    public function description(): string
    {
        return 'Items on this institute\'s inventory item master, with their category, the stock figure RECORDED '
            .'against each and the reorder level. THIS ESTATE KEEPS NO RUNNING STOCK BALANCE: the column is '
            .'increased by direct purchase and never decreased when stock is issued, so it overstates what is '
            .'on the shelf. Nothing it returns may be described as in stock or out of stock. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer', 'minimum' => 1],
                'sub_category_id' => ['type' => 'integer', 'minimum' => 1],
                'item_type_id' => ['type' => 'integer', 'minimum' => 1],
                'item_id' => ['type' => 'integer', 'minimum' => 1],
                'at_or_below_minimum_only' => ['type' => 'boolean', 'description' => 'Only items whose RECORDED figure is at or below the recorded reorder level. Not a stock count.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
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

        return $this->service->items($context, $arguments);
    }
}
