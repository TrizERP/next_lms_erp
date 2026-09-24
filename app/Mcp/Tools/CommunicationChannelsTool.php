<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\CommunicationService;

/**
 * Every channel, whether its log exists here, and whether it records delivery at all.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class CommunicationChannelsTool extends AbstractMcpTool
{
    public function __construct(private readonly CommunicationService $service)
    {
    }

    public function name(): string
    {
        return 'communication.channels';
    }

    public function description(): string
    {
        return 'Every communication channel, whether its log exists on this estate, how many messages it '
            .'holds for this institute and year, when it was first and last used, and whether it records a '
            .'delivery outcome at all. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'communication.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->channels($context, $arguments);
    }
}
