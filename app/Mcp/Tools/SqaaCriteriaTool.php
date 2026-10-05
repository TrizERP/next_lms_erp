<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\SqaaService;

/**
 * The quality assurance criteria tree, by level.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class SqaaCriteriaTool extends AbstractMcpTool
{
    public function __construct(private readonly SqaaService $service)
    {
    }

    public function name(): string
    {
        return 'sqaa.criteria';
    }

    public function description(): string
    {
        return 'The SQAA criteria this institute is assessed against, with their level and parent in the tree. '
            .'NOTHING HERE SCORES A SCHOOL: no rubric, weighting or grade boundary is recorded anywhere, so '
            .'never state a score, rating, band or readiness for assessment. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'level' => ['type' => 'integer', 'minimum' => 0],
                'parent_id' => ['type' => 'integer', 'minimum' => 1],
                'search_text' => ['type' => 'string', 'description' => 'Free text to match.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'sqaa.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->criteria($context, $arguments);
    }
}
