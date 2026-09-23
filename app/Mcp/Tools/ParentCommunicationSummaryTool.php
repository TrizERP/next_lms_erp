<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentCommunicationService;

/**
 * Parent messages counted by month and by whether they have been answered.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ParentCommunicationSummaryTool extends AbstractMcpTool
{
    public function __construct(private readonly ParentCommunicationService $service)
    {
    }

    public function name(): string
    {
        return 'parent_communication.summary';
    }

    public function description(): string
    {
        return 'Messages from parents counted by month and by whether a reply is recorded, with the date of the '
            .'oldest unanswered one. This returns NO message body at all: a cohort figure is the one place a '
            .'named family\'s letter has no business appearing. Changes nothing.';
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
        return ['risk' => 'read', 'required_permission' => 'parent_communication.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->summary($context, $arguments);
    }
}
