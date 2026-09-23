<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\CircularService;
use App\Services\Mcp\McpRequestContext;

/**
 * The circular types available, with how many circulars each carries here.
 *
 * `circular_type` is an estate-wide vocabulary and carries no tenant column, so the
 * counts beside it are scoped to the caller's institute and academic year rather than
 * being the table's own totals.
 */
class CircularsTypesTool extends AbstractMcpTool
{
    public function __construct(private readonly CircularService $service)
    {
    }

    public function name(): string
    {
        return 'circulars.types';
    }

    public function description(): string
    {
        return 'The circular types available, with how many circulars this institute published under '
            .'each in this academic year. Use it to resolve a type named in a question into the id '
            .'circulars.list filters on. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'circular.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->types($context, $arguments);
    }
}
