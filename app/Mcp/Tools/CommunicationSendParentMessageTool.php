<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Mcp\Concerns\RequiresAdministrator;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentMessageService;

/**
 * Message students' parents by SMS or WhatsApp, after explicit approval.
 *
 * Two calls by design, like every confirmable tool here: the first returns who would be messaged, the
 * literal wording and a fingerprint of that list and sends nothing; the second spends the confirmation
 * and sends — and only if the list is still the one that was approved.
 *
 * Administrators only, by the ERP's own definition (`AdministratorCheck`). SMS goes through the institute's
 * saved gateway and WhatsApp through its saved Cloud API credentials; neither is ever returned or logged.
 */
class CommunicationSendParentMessageTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    use RequiresAdministrator;

    public function __construct(private readonly ParentMessageService $service)
    {
    }

    public function name(): string
    {
        return 'communication.send_parent_message';
    }

    public function description(): string
    {
        return 'Send a message to the parents of named students or of a class, by SMS or by WhatsApp. Previews first '
            .'(recipients with masked numbers, the exact wording, the channel and the provider) and sends only after '
            .'explicit confirmation of that list. Students with no usable mobile number are listed and not contacted. '
            .'"Sent" means the provider accepted the message, never that it was delivered. Use {name} in the wording '
            .'for each student\'s first name. Requires the channel to be configured for the institute.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel' => ['type' => 'string', 'enum' => ParentMessageService::CHANNELS, 'description' => 'sms or whatsapp.'],
                'message' => ['type' => 'string', 'maxLength' => ParentMessageService::WHATSAPP_MAX_LENGTH, 'description' => 'The wording. SMS is limited to '.ParentMessageService::SMS_MAX_LENGTH.' characters.'],
                'student_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => ParentMessageService::MAX_RECIPIENTS],
                'standard_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Message every student in this class when no student_ids are given.'],
                'division_id' => ['type' => 'integer', 'minimum' => 1],
                'approved_fingerprint' => ['type' => 'string', 'description' => 'The fingerprint of the previewed list, channel and wording that was approved. Required to send.'],
            ],
            'required' => ['channel', 'message'],
            'additionalProperties' => false,
        ];
    }

    protected function isReadOnly(): bool
    {
        return false;
    }

    protected function toolAnnotations(): array
    {
        return [
            'risk' => 'approval',
            'required_permission' => 'communication.send',
            'requires_confirmation' => true,
        ];
    }

    protected function administratorOnlyMessage(): string
    {
        return 'Sending messages to parents is limited to school administrators, and your login is not one.';
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return $this->preview($arguments, $context);
    }

    public function preview(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->preview($context, $arguments);
    }

    public function executeConfirmed(array $arguments, McpRequestContext $context, array $confirmation): array
    {
        $this->authorize($context);

        $payload = is_array($confirmation['arguments'] ?? null) ? $confirmation['arguments'] : $arguments;

        return $this->service->send($context, $payload, isset($payload['approved_fingerprint']) ? (string) $payload['approved_fingerprint'] : null);
    }
}
