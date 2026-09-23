<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TransportService;

/**
 * Transport routes with their stops and the vehicles assigned to run them.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class TransportRoutesTool extends AbstractMcpTool
{
    public function __construct(private readonly TransportService $service)
    {
    }

    public function name(): string
    {
        return 'transport.routes';
    }

    public function description(): string
    {
        return 'Transport routes for this institute and academic year, each with its scheduled times, the stops '
            .'it calls at with their pickup and drop times, and the vehicles assigned to run it. The times are '
            .'the SCHEDULE; this system records no departure or arrival that actually happened, no delay and '
            .'no live position. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'route_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->routes($context, $arguments);
    }
}
