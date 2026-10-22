<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Mcp\Concerns\RequiresAdministrator;
use App\Mcp\ConfirmableMcpToolInterface;
use App\Services\Mcp\FeesReminderService;
use App\Services\Mcp\McpRequestContext;

/**
 * Email fee payment reminders to the students who owe the most, after explicit approval.
 *
 * Two calls by design, like every confirmable tool here. The first returns who would be
 * emailed, what each owes, the deadline and the literal message, and sends nothing. The
 * second spends the confirmation and sends — and only if the list the ledger gives now is
 * the list the person approved (`approved_fingerprint`).
 *
 * Administrators only: it writes to families, so it is stricter than the read tools. "Administrator"
 * is the ERP's own definition (`AdministratorCheck`), because the MCP gate's `admin` role is empty
 * for every user in this estate.
 */
class FeesSendRemindersTool extends AbstractMcpTool implements ConfirmableMcpToolInterface
{
    use RequiresAdministrator;

    public function __construct(private readonly FeesReminderService $service)
    {
    }

    public function name(): string
    {
        return 'fees.send_reminders';
    }

    public function description(): string
    {
        return 'Send a fee payment reminder, with a payment deadline, to students who owe fees, by email, by SMS or by WhatsApp. '
            .'Previews first (recipients, amounts, deadline, channel and the exact message) and sends only after '
            .'explicit confirmation of that list. Students with no usable email or mobile number are listed and not '
            .'contacted. SMS goes through the institute\'s own gateway and WhatsApp through its WhatsApp Business account; "sent" means '
            .'the provider accepted it, never that it was delivered (WhatsApp reports delivered and read later). Records the outcome of every reminder.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deadline' => ['type' => 'string', 'description' => 'Payment deadline, YYYY-MM-DD; today or later.'],
                'top' => ['type' => 'integer', 'minimum' => 1, 'maximum' => FeesReminderService::MAX_RECIPIENTS, 'default' => FeesReminderService::DEFAULT_TOP],
                'min_outstanding' => ['type' => 'number', 'minimum' => 0, 'description' => 'Only students owing at least this much.'],
                'standard_id' => ['type' => 'integer', 'minimum' => 1],
                'student_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => FeesReminderService::MAX_RECIPIENTS],
                'channel' => ['type' => 'string', 'enum' => FeesReminderService::CHANNELS, 'default' => 'email'],
                'message' => ['type' => 'string', 'maxLength' => 480, 'description' => 'Optional wording instead of the standard reminder. {name}, {amount} and {deadline} are replaced with each student\'s own values.'],
                'approved_fingerprint' => ['type' => 'string', 'description' => 'The fingerprint of the previewed list, channel and wording that was approved. Required to send.'],
            ],
            'required' => ['deadline'],
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
            'required_permission' => 'fees.remind',
            'requires_confirmation' => true,
        ];
    }

    protected function administratorOnlyMessage(): string
    {
        return 'Sending fee reminders is limited to school administrators, and your login is not one. '
            .'An administrator can send them; you can still see who owes and how much.';
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
