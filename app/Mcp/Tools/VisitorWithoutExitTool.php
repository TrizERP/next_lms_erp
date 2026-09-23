<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\VisitorService;

/**
 * Visits with an entry time and no exit time recorded, oldest first.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class VisitorWithoutExitTool extends AbstractMcpTool
{
    public function __construct(private readonly VisitorService $service)
    {
    }

    public function name(): string
    {
        return 'visitor.without_exit';
    }

    public function description(): string
    {
        return 'Visits to this institute with an entry time recorded and no exit time, oldest first, with a '
            .'count of how many are from a day already past. This is deliberately NOT a list of people '
            .'currently on the premises: the visitor may still be on site or may have left without signing '
            .'out, and the register cannot tell the difference. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'visitor_type_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->withoutExit($context, $arguments);
    }
}
