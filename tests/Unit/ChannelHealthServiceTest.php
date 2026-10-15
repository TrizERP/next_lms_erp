<?php

namespace Tests\Unit;

use App\Services\Mcp\ChannelHealthService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The channel health check: what it reports when the providers say each thing.
 *
 * Meta and the SMS gateway are faked, and the saved credentials, the gateway row and the recent-message history are replaced, so
 * nothing is sent, nothing is read from the school's data and no real credential is used. The WhatsApp error cases are the
 * actual causes a school hits: an invalid token, a number not connected, a declined display name.
 */
class ChannelHealthServiceTest extends TestCase
{
    private const GATEWAY = ['url' => 'https://login.smsforyou.biz/V2/http-api.php?', 'pram' => 'apikey=SECRETKEY&senderid=SMSEDU&format=json', 'last_var' => '.(Generated at CMA)', 'sub_institute_id' => 1];

    private const WA = ['cloud_api_access_token' => 'SECRET-TOKEN', 'cloud_api_phone_number_id' => '111'];

    private function context(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    /**
     * @param  array<int, array<string, mixed>>  $recentSms
     * @param  array<int, array<string, mixed>>  $recentWhatsapp
     */
    private function service(?array $gateway, ?array $whatsapp, array $recentSms = [], array $recentWhatsapp = [], bool $receipts = false): ChannelHealthService
    {
        return new class($gateway, $whatsapp, $recentSms, $recentWhatsapp, $receipts) extends ChannelHealthService
        {
            public function __construct(private ?array $gw, private ?array $wa, private array $sms, private array $whats, private bool $receipts)
            {
            }

            protected function smsGateway(McpRequestContext $context): ?array
            {
                return $this->gw;
            }

            protected function whatsappConfiguration(McpRequestContext $context): ?array
            {
                return $this->wa;
            }

            protected function whatsappReceiptsArriving(): bool
            {
                return $this->receipts;
            }

            protected function reportedWhatsappStatus(string $providerId): ?string
            {
                return null;
            }

            // The recent-message history, replaced: the real one is read from the audit table.
            protected function recentAcceptedFor(McpRequestContext $context, string $channel): array
            {
                return $channel === 'sms' ? $this->sms : $this->whats;
            }
        };
    }

    private function fakeMeta(bool $valid = true, string $status = 'CONNECTED', string $nameStatus = 'APPROVED', string $quality = 'GREEN', array $scopes = ['whatsapp_business_messaging', 'whatsapp_business_management']): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => $valid, 'type' => 'SYSTEM_USER', 'expires_at' => 0, 'scopes' => $scopes]], 200),
            'graph.facebook.com/*/111*' => Http::response(['id' => '111', 'display_phone_number' => '+91 99099 06512', 'verified_name' => 'School', 'quality_rating' => $quality, 'name_status' => $nameStatus, 'status' => $status, 'account_mode' => 'LIVE'], 200),
        ]);
    }

    /** @param  array<string, mixed>  $result */
    private function problems(array $result): array
    {
        return $result['data']['problems'];
    }

    public function test_a_healthy_whatsapp_setup_reports_no_problem(): void
    {
        $this->fakeMeta();

        $result = $this->service(self::GATEWAY, self::WA, [], [], true)->check($this->context(), 'whatsapp');

        $this->assertTrue($result['success']);
        $this->assertSame([], $this->problems($result));
        $this->assertSame('parents_', $result['data']['whatsapp']['template']['name']);
        $this->assertSame(['en_US', 'en', 'en_GB'], $result['data']['whatsapp']['template']['languages_tried']);
        $this->assertSame('SYSTEM_USER', $result['data']['whatsapp']['token']['type']);
        $this->assertNull($result['data']['sms'], 'only the channel asked about is checked');
    }

    /** @return array<string, array{0:array<string, mixed>, 1:string}> */
    public static function whatsappFaults(): array
    {
        return [
            'invalid token' => [['valid' => false], 'INVALID'],
            'no messaging permission' => [['scopes' => ['public_profile']], 'whatsapp_business_messaging'],
            'number not connected' => [['status' => 'DISCONNECTED'], 'not CONNECTED'],
            'display name declined' => [['nameStatus' => 'DECLINED'], 'DECLINED'],
            'quality red' => [['quality' => 'RED'], 'quality rating is RED'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('whatsappFaults')]
    public function test_each_whatsapp_fault_is_named_with_what_to_do(array $fault, string $expected): void
    {
        $this->fakeMeta(...$fault);

        $result = $this->service(self::GATEWAY, self::WA, [], [], true)->check($this->context(), 'whatsapp');

        $this->assertStringContainsString($expected, implode(' ', $this->problems($result)));
    }

    public function test_missing_receipts_are_reported_with_the_exact_setup(): void
    {
        $this->fakeMeta();

        $result = $this->service(self::GATEWAY, self::WA, [], [], false)->check($this->context(), 'whatsapp');
        $text = implode(' ', $this->problems($result));

        $this->assertStringContainsString('No WhatsApp delivery receipt', $text);
        $this->assertStringContainsString('WHATSAPP_WEBHOOK_VERIFY_TOKEN', $text);
        $this->assertStringContainsString('/api/whatsapp/webhook', $text);
        $this->assertFalse($result['data']['whatsapp']['webhook']['receipts_arriving']);
    }

    public function test_unconfigured_channels_are_refused_with_what_is_missing(): void
    {
        Http::preventStrayRequests();

        $result = $this->service(null, null)->check($this->context());

        $this->assertStringContainsString('No SMS gateway is saved', implode(' ', $this->problems($result)));
        $this->assertStringContainsString('WhatsApp is not configured', implode(' ', $this->problems($result)));
        $this->assertFalse($result['data']['sms']['configured']);
        $this->assertFalse($result['data']['whatsapp']['configured']);
    }

    public function test_meta_being_unreachable_is_reported_as_unchecked_never_as_valid(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response('', 500)]);

        $result = $this->service(self::GATEWAY, self::WA, [], [], true)->check($this->context(), 'whatsapp');

        $this->assertFalse($result['data']['whatsapp']['token']['checked']);
        $this->assertStringContainsString('Meta did not accept', implode(' ', $this->problems($result)));
    }

    /** @return array<string, mixed> */
    private function sms(string $id, string $sentAt): array
    {
        return ['student_name' => 'ASHA', 'provider_id' => $id, 'sent_at' => $sentAt, 'reference' => 'FR-'.$id];
    }

    public function test_several_sms_stuck_at_submitted_are_a_provider_side_problem_and_the_key_never_leaks(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.smsforyou.biz/*' => Http::response(['status' => 'OK', 'data' => [['status_desc' => 'Submitted', 'delivered_date' => '1970-01-01 05:30 am']]], 200)]);

        $recent = [$this->sms('1', now()->subHours(3)->toDateTimeString()), $this->sms('2', now()->subHours(2)->toDateTimeString()), $this->sms('3', now()->subHour()->toDateTimeString())];
        $result = $this->service(self::GATEWAY, null, $recent)->check($this->context(), 'sms');
        $text = implode(' ', $this->problems($result));

        $this->assertStringContainsString('still "Submitted"', $text);
        $this->assertStringContainsString('sender id (SMSEDU)', $text);
        $this->assertStringContainsString('DLT template', $text);
        $this->assertSame(3, $result['data']['sms']['delivery']['not_confirmed']);
        $this->assertStringContainsString('not_confirmed', json_encode($result['data']['sms']['recent']));
        $this->assertStringNotContainsString('SECRETKEY', json_encode($result));
        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode($result));
    }

    public function test_healthy_is_claimed_only_when_every_recent_message_was_reported_delivered(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.smsforyou.biz/*' => Http::response(['status' => 'OK', 'data' => [['status_desc' => 'Delivered', 'delivered_date' => '2026-10-15 16:10:00']]], 200)]);

        $delivered = $this->service(self::GATEWAY, null, [$this->sms('1', now()->subHour()->toDateTimeString())])->check($this->context(), 'sms');
        $this->assertStringContainsString('reported delivered', $delivered['message']);
        $this->assertSame([], $this->problems($delivered));

    }

    public function test_a_message_still_submitted_is_never_called_healthy(): void
    {
        Http::preventStrayRequests();
        Http::fake(['login.smsforyou.biz/*' => Http::response(['status' => 'OK', 'data' => [['status_desc' => 'Submitted', 'delivered_date' => '1970-01-01 05:30 am']]], 200)]);
        $stuck = $this->service(self::GATEWAY, null, [$this->sms('1', now()->subHour()->toDateTimeString())])->check($this->context(), 'sms');
        $this->assertStringContainsString('delivery is unproven', $stuck['message']);
        $this->assertStringNotContainsString('healthy', strtolower($stuck['message']));
    }
}
