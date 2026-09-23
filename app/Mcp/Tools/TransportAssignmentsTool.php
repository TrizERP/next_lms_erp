<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TransportService;

/**
 * Students assigned to transport, with their bus and stop for each leg.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TransportAssignmentsTool extends AbstractMcpTool
{
    public function __construct(private readonly TransportService $service)
    {
    }

    public function name(): string
    {
        return 'transport.assignments';
    }

    public function description(): string
    {
        return 'Students assigned to transport in this institute and academic year, each with the bus and stop '
            .'for the morning and afternoon legs, the distance and the transport fee written on the mapping. '
            .'An assignment is a plan, not a journey: nothing records that a child boarded, and whether the '
            .'fee was paid is a Fees question that is not readable here. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'vehicle_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Matches the morning bus.'],
                'stop_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Matches the morning stop.'],
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

        return $this->service->assignments($context, $arguments);
    }
}
