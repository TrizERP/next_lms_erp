<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\SqaaService;

/**
 * Evidence uploaded against the quality assurance document slots.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class SqaaEvidenceTool extends AbstractMcpTool
{
    public function __construct(private readonly SqaaService $service)
    {
    }

    public function name(): string
    {
        return 'sqaa.evidence';
    }

    public function description(): string
    {
        return 'Evidence recorded against this institute\'s SQAA document slots, with whether it was marked '
            .'available and whether a file is actually attached — two different facts. The number of slots '
            .'defined is returned beside the number of evidence rows and the two must always be reported '
            .'together. Nothing here scores a school. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'menu_id' => ['type' => 'integer', 'minimum' => 1],
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

        return $this->service->evidence($context, $arguments);
    }
}
