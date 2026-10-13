<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AlumniOverviewService;
use App\Services\Mcp\McpRequestContext;

/**
 * Alumni events with date, venue, mode, capacity, status and registration count. Attendance is not recorded.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class AlumniEventsTool extends AbstractMcpTool
{
    public function __construct(private readonly AlumniOverviewService $service)
    {
    }

    public function name(): string
    {
        return 'alumni.events';
    }

    public function description(): string
    {
        return 'Alumni events with date, venue, mode, capacity, status and registration count. Attendance is not recorded. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'alumni.read'];
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

        return $this->service->events($context, $arguments);
    }
}
