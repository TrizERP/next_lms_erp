<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\CommunicationService;

/**
 * What the school has sent, across every channel, with the delivery caveat stated.
 *
 * Read-only by construction, and no argument can widen it: the institute and the academic
 * year come from the caller's token inside the service.
 */
class CommunicationMessagesTool extends AbstractMcpTool
{
    public function __construct(private readonly CommunicationService $service)
    {
    }

    public function name(): string
    {
        return 'communication.messages';
    }

    public function description(): string
    {
        return 'Messages this institute has sent in this academic year across SMS to parents, SMS to staff, '
            .'WhatsApp and app notifications, newest first, with a per-channel breakdown. Only WhatsApp '
            .'records a delivery outcome; for every other channel the delivery status is null, meaning the '
            .'send was logged rather than that it arrived. Changes nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel' => ['type' => 'string', 'enum' => ['sms_parent', 'sms_staff', 'whatsapp', 'app_notification'], 'description' => 'Omit for every channel.'],
                'student_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Applied only to channels that record a student.'],
                'search_text' => ['type' => 'string', 'description' => 'Match the message body.'],
                'from_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'to_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, inclusive.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
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

        return $this->service->messages($context, $arguments);
    }
}
