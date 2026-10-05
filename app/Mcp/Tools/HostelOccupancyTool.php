<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\HostelOccupancyService;
use App\Services\Mcp\McpRequestContext;

/**
 * Every hostel, with its rooms and how many of them are occupied this year.
 *
 * Reports no capacity and no percentage: `hostel_room_master` holds no bed count in this
 * estate, so a "78% full" figure would be invented. See `HostelOccupancyService`.
 */
class HostelOccupancyTool extends AbstractMcpTool
{
    public function __construct(private readonly HostelOccupancyService $service)
    {
    }

    public function name(): string
    {
        return 'hostel.occupancy';
    }

    public function description(): string
    {
        return 'The hostels this institute runs, with the warden on each, how many rooms it has, how '
            .'many are occupied this academic year and how many people are allocated. Rooms carry no '
            .'recorded bed capacity, so no percentage of capacity is reported. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hostel_id' => ['type' => 'integer', 'minimum' => 1],
                'hostel_name' => ['type' => 'string', 'description' => 'Match part of the hostel name.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'hostel.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->occupancy($context, $arguments);
    }
}
