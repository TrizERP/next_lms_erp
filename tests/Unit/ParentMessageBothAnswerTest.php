<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\ParentMessageAnswer;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/** What the chat shows when a parent message goes by both SMS and WhatsApp. */
class ParentMessageBothAnswerTest extends TestCase
{
    private function compose(string $state, array $data, string $message = 'm'): StageContext
    {
        $context = new StageContext('q', new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false), ModuleCapability::general());
        (new ParentMessageAnswer(new AnswerComposer()))->compose($context, ['state' => $state, 'data' => $data, 'message' => $message, 'pending' => null]);

        return $context;
    }

    private function text(StageContext $context): string
    {
        return json_encode([$context->headline(), $context->sections(), $context->actions()], JSON_UNESCAPED_UNICODE);
    }

    public function test_the_channel_choice_offers_all_three_options(): void
    {
        $text = $this->text($this->compose('need_channel', []));

        $this->assertStringContainsString('Send SMS and WhatsApp', $text);
        $this->assertStringContainsString('Send SMS', $text);
        $this->assertStringContainsString('Send WhatsApp Message', $text);
    }

    public function test_the_preview_shows_each_channels_own_message_and_names_an_unavailable_channel(): void
    {
        $part = fn (string $label, string $final) => [
            'channel_label' => $label, 'final_message' => $final, 'template' => ['kind' => 'none'], 'warnings' => [],
        ];
        $text = $this->text($this->compose('ready', [
            'channel' => 'both', 'channel_label' => 'SMS and WhatsApp', 'recipient_count' => 2,
            'recipients' => [['name' => 'Asha', 'contact_masked' => '••••3210']], 'unreachable' => [],
            'channels' => ['sms' => $part('SMS', 'Hi Asha SMS-TEXT'), 'whatsapp' => $part('WhatsApp', 'Hi Asha WA-TEXT')],
            'unavailable' => [],
        ]));

        $this->assertStringContainsString('SMS-TEXT', $text);
        $this->assertStringContainsString('WA-TEXT', $text);
        $this->assertStringContainsString('SMS and WhatsApp', $text);

        $text = $this->text($this->compose('ready', [
            'channel' => 'both', 'channel_label' => 'SMS', 'recipient_count' => 1, 'recipients' => [], 'unreachable' => [],
            'channels' => ['sms' => $part('SMS', 'only sms')], 'unavailable' => ['whatsapp' => 'WhatsApp is not configured for this institute.'],
        ]));

        $this->assertStringContainsString('WhatsApp will NOT be sent', $text);
        $this->assertStringContainsString('not configured', $text);
    }

    public function test_the_result_reports_each_channel_so_one_failure_is_not_hidden(): void
    {
        $text = $this->text($this->compose('sent', [
            'channel' => 'both', 'channel_label' => 'SMS and WhatsApp', 'reference' => 'PM-1 / PM-2', 'sent' => 1, 'failed' => 1, 'skipped' => 0,
            'results' => [['name' => 'Asha — SMS', 'status' => 'failed', 'detail' => 'gateway said no'], ['name' => 'Asha — WhatsApp', 'status' => 'sent', 'detail' => 'wamid']],
            'channels' => ['sms' => ['ok' => false, 'message' => 'No message could be sent.'], 'whatsapp' => ['ok' => true, 'message' => '1 accepted']],
        ], 'SMS: No message could be sent. WhatsApp: 1 accepted'));

        $this->assertStringContainsString('NOT sent', $text);
        $this->assertStringContainsString('Accepted', $text);
        $this->assertStringContainsString('gateway said no', $text);
        $this->assertStringContainsString('not proof it reached the phone', $text);
    }

    public function test_the_wording_step_shows_each_channels_provider_and_template(): void
    {
        $text = $this->text($this->compose('need_message', ['details' => [
            'channel_label' => 'SMS and WhatsApp', 'students' => [['name' => 'Asha']], 'limit' => 480, 'variables' => [],
            'channels' => [
                'sms' => ['provider' => ['name' => 'SMS gateway', 'host' => 'gw.test'], 'template' => ['kind' => 'signature', 'text' => '.Sig']],
                'whatsapp' => ['provider' => ['name' => 'WhatsApp Cloud API', 'host' => 'graph.facebook.com'], 'template' => ['kind' => 'cloud_template', 'name' => 'parents_']],
            ],
            'unavailable' => [],
        ]]));

        $this->assertStringContainsString('gw.test', $text);
        $this->assertStringContainsString('parents_', $text);
        $this->assertStringContainsString('.Sig', $text);
    }
}
