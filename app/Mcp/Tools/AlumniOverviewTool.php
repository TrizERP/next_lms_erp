<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\AlumniOverviewService;
use App\Services\Mcp\McpRequestContext;

/**
 * Alumni counted by status, passing year and employment status, with counts of events, mentorship requests, opportunities, surveys and donations by status. Donations are totals only.
 *
 * Read-only by construction; the institute comes from the caller's token inside the service.
 */
class AlumniOverviewTool extends AbstractMcpTool
{
    public function __construct(private readonly AlumniOverviewService $service)
    {
    }

    public function name(): string
    {
        return 'alumni.overview';
    }

    public function description(): string
    {
        return 'Alumni counted by status, passing year and employment status, with counts of events, mentorship requests, opportunities, surveys and donations by status. Donations are totals only. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [

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

        return $this->service->overview($context, $arguments);
    }
}
