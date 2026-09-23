<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\VisitorService;

/**
 * The visitor register: who came, who they came to meet, and the entry and exit times recorded.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class VisitorVisitsTool extends AbstractMcpTool
{
    public function __construct(private readonly VisitorService $service)
    {
    }

    public function name(): string
    {
        return 'visitor.visits';
    }

    public function description(): string
    {
        return 'The visitor register for this institute: one row per visit, with the visitor, where they came '
            .'from, who they came to meet, the purpose, the date and the entry and exit times recorded. A '
            .'missing exit time means no exit was RECORDED, never that the person is still in the building. '
            .'This table records no approval of any kind. The Hostel module keeps a separate visitor register '
            .'which is not included. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'visitor_type_id' => ['type' => 'integer', 'minimum' => 1],
                'appointment_type' => ['type' => 'string', 'description' => 'As stored, e.g. Direct.'],
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
        return ['risk' => 'read', 'required_permission' => 'visitor.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->visits($context, $arguments);
    }
}
