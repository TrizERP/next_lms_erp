<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentCommunicationService;

/**
 * Messages parents wrote to the school, and whether anybody has replied.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class ParentCommunicationMessagesTool extends AbstractMcpTool
{
    public function __construct(private readonly ParentCommunicationService $service)
    {
    }

    public function name(): string
    {
        return 'parent_communication.messages';
    }

    public function description(): string
    {
        return 'Messages PARENTS wrote to this school, with the student each concerns, the date and whether a '
            .'reply is recorded. This is the inbound direction and is not the Communication module, which '
            .'records what the school sends. The message body is returned only for a read naming one student '
            .'or one message. A message with no reply means nobody has answered it yet. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'message_id' => ['type' => 'integer', 'minimum' => 1],
                'state' => ['type' => 'string', 'enum' => ['any', 'answered', 'unanswered'], 'default' => 'any'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'search_text' => ['type' => 'string', 'description' => 'Matches the title only, never the message body.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->messages($context, $arguments);
    }
}
