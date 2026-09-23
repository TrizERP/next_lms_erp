<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TransportService;

/**
 * Vehicles with seating capacity against students assigned, counted separately for each leg.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TransportVehiclesTool extends AbstractMcpTool
{
    public function __construct(private readonly TransportService $service)
    {
    }

    public function name(): string
    {
        return 'transport.vehicles';
    }

    public function description(): string
    {
        return 'Transport vehicles for this institute with their seating capacity, driver, conductor, shift and '
            .'routes, and the number of students assigned to each — counted SEPARATELY for the morning and '
            .'afternoon legs, which are different trips and must never be added together. `over_capacity` is '
            .'true only where a seat count is recorded and one leg exceeds it. This system records no '
            .'boarding, live position, trip log, or fitness, insurance or permit expiry. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vehicle_id' => ['type' => 'integer', 'minimum' => 1],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'transport.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->vehicles($context, $arguments);
    }
}
