<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\HostelOccupancyService;
use App\Services\Mcp\McpRequestContext;

/**
 * Rooms with nobody allocated to them this academic year.
 *
 * "Available" is the estate's own definition, taken from the existing room report: no
 * allocation row for this institute and year. A partly filled room is not offered as
 * available, because no bed count exists to say it has room left.
 */
class HostelAvailableRoomsTool extends AbstractMcpTool
{
    public function __construct(private readonly HostelOccupancyService $service)
    {
    }

    public function name(): string
    {
        return 'hostel.available_rooms';
    }

    public function description(): string
    {
        return 'Hostel rooms with no allocation for this institute in this academic year, with their '
            .'floor, building, hostel and warden. A partly filled room is not listed, because rooms '
            .'carry no recorded bed capacity. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hostel_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->availableRooms($context, $arguments);
    }
}
