<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ComplaintService;
use App\Services\Mcp\McpRequestContext;

/**
 * Complaints with their status, who raised them and which group they were assigned to.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ComplaintsListTool extends AbstractMcpTool
{
    public function __construct(private readonly ComplaintService $service)
    {
    }

    public function name(): string
    {
        return 'complaints.list';
    }

    public function description(): string
    {
        return 'Complaints recorded for this institute, with the title, date, who raised it, the group it was '
            .'assigned to and its status. THE COLUMN NAMED `COMPLAINT_SOLUTION` IS THE STATUS FIELD, not a '
            .'resolution: no resolution text exists anywhere on this table. This table also records no '
            .'priority, category, due date, SLA or escalation. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'state' => ['type' => 'string', 'enum' => ['any', 'open', 'closed'], 'default' => 'any'],
                'user_group_id' => ['type' => 'integer', 'minimum' => 1],
                'raised_by' => ['type' => 'integer', 'minimum' => 1],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->list($context, $arguments);
    }
}
