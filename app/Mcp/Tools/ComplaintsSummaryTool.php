<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ComplaintService;
use App\Services\Mcp\McpRequestContext;

/**
 * Complaints counted by status and by the group they were assigned to.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ComplaintsSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly ComplaintService $service)
    {
    }

    public function name(): string
    {
        return 'complaints.summary';
    }

    public function description(): string
    {
        return 'Complaints for this institute counted by status and by assigned group, over the whole filtered '
            .'set. Groups are reported as ids because this table records no department name. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_group_id' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'complaint.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
