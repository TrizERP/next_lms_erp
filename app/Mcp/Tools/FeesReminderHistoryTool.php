<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\FeesReminderService;
use App\Services\Mcp\McpRequestContext;

/**
 * Fee reminders already sent or attempted, with each one's status.
 *
 * Read-only: it reads back the rows `fees.send_reminders` wrote to the audit ledger for the
 * caller's institute. "sent" means the mail server accepted the message, nothing more.
 */
class FeesReminderHistoryTool extends AbstractMcpTool
{
    public function __construct(private readonly FeesReminderService $service)
    {
    }

    public function name(): string
    {
        return 'fees.reminder_history';
    }

    public function description(): string
    {
        return 'Fee reminders already sent from here, newest first, with each one\'s student, amount, deadline and '
            .'status (sent = accepted by the mail server, failed, or skipped for no email on record). '
            .'Each record carries the gateway\'s reply, the recipient (masked), the send reference and a delivery state: '
            .'accepted records are "unknown" because neither a mail server nor this SMS gateway reports delivery back. '
            .'It does not record whether a message was read. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'student_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['sent', 'failed', 'skipped']],
                'channel' => ['type' => 'string', 'enum' => ['email', 'sms']],
                'date' => ['type' => 'string', 'description' => 'Only reminders recorded on this date, YYYY-MM-DD.'],
                'batch' => ['type' => 'string', 'description' => 'Only this send, by its reference such as FR-20261015-abcde.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'fees.collect'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->history($context, $arguments);
    }
}
