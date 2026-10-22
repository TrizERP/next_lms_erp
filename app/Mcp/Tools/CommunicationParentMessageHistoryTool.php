<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentMessageService;

/**
 * Parent messages (SMS and WhatsApp) already sent from the assistant, read back from the saved record.
 *
 * The other half of `communication.send_parent_message`: it answers "show the message status" and "was the
 * WhatsApp delivered?" from what was actually recorded, with the provider's reply, the recipient (masked) and a
 * delivery state that claims no more than is known. Changes nothing.
 */
class CommunicationParentMessageHistoryTool extends AbstractMcpTool
{
    public function __construct(private readonly ParentMessageService $service)
    {
    }

    public function name(): string
    {
        return 'communication.parent_message_history';
    }

    public function description(): string
    {
        return 'Parent messages sent from here by SMS or WhatsApp, newest first: student, channel, wording, the provider\'s reply, '
            .'the recipient (masked), the send reference and a delivery state. An SMS gateway sends no delivery receipt, so an '
            .'accepted SMS is "unknown"; a WhatsApp message shows delivered or read once WhatsApp has reported it, else "pending". '
            .'Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'channel' => ['type' => 'string', 'enum' => ParentMessageService::CHANNELS],
                'status' => ['type' => 'string', 'enum' => ['sent', 'failed', 'skipped']],
                'batch' => ['type' => 'string', 'description' => 'Only this send, by its reference such as PM-20261015-abcde.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'communication.read'];
    }

    /** @return array<int, string> */
    protected function allowedRoles(): array
    {
        return ['admin', 'staff'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->history($context, $arguments);
    }
}
