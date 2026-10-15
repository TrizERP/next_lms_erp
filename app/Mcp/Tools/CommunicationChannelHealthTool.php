<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ChannelHealthService;
use App\Services\Mcp\McpRequestContext;

/**
 * Whether SMS and WhatsApp can actually reach parents, asked of the providers themselves.
 *
 * Read-only: nothing is sent and nothing is written. Answers "check whether the WhatsApp configuration and template are valid",
 * "is SMS set up properly" and "why are the messages not arriving" from the saved configuration, the providers' own answers
 * (Meta's token and number status, the SMS gateway's delivery reports) and the saved send history. Credentials are used on the
 * server for the checks and are never returned.
 */
class CommunicationChannelHealthTool extends AbstractMcpTool
{
    public function __construct(private readonly ChannelHealthService $service)
    {
    }

    public function name(): string
    {
        return 'communication.channel_health';
    }

    public function description(): string
    {
        return 'Check whether SMS and WhatsApp can actually reach parents: the saved configuration, Meta\'s answer on the WhatsApp token '
            .'and number (status, quality, display name), the approved template the reminders use, whether WhatsApp delivery receipts '
            .'reach this system, and what the SMS gateway\'s and WhatsApp\'s own reports say about the last few accepted messages. '
            .'Names the problems it finds. Changes nothing and sends nothing.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel' => ['type' => 'string', 'enum' => ['sms', 'whatsapp'], 'description' => 'Check one channel; omit for both.'],
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

        return $this->service->check($context, isset($arguments['channel']) ? (string) $arguments['channel'] : null);
    }
}
