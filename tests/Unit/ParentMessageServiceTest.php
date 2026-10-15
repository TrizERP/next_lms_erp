<?php

namespace Tests\Unit;

use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentMessageService;
use PHPUnit\Framework\TestCase;

/**
 * The chatbot's parent messaging: preview, approval, and what "sent" is allowed to mean.
 *
 * NO NETWORK, NO DATABASE: students, gateway settings and both senders are replaced, so nothing is sent
 * and nothing is written. What is under test is the decision-making around the providers.
 */
class ParentMessageServiceTest extends TestCase
{
    private function context(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    /** @param  array<string, mixed>  $overrides */
    private function service(array $overrides = []): FakeParentMessageService
    {
        $service = new FakeParentMessageService($this->createMock(AiAuditLogger::class));

        foreach ($overrides as $key => $value) {
            $service->{$key} = $value;
        }

        return $service;
    }

    private function sms(string $text = 'School is closed tomorrow, {name}.'): array
    {
        return ['channel' => 'sms', 'message' => $text, 'student_ids' => [1, 2, 3]];
    }

    private function approved(FakeParentMessageService $service, array $arguments): string
    {
        $fingerprint = $service->preview($this->context(), $arguments)['data']['fingerprint'];
        $this->assertNotEmpty($fingerprint);

        return $fingerprint;
    }

    public function test_preview_shows_masked_numbers_the_literal_wording_and_sends_nothing(): void
    {
        $service = $this->service();
        $preview = $service->preview($this->context(), $this->sms());

        $this->assertTrue($preview['success']);
        $this->assertSame(2, $preview['data']['recipient_count']);
        $this->assertSame(1, $preview['data']['unreachable_count']);
        $this->assertSame('School is closed tomorrow, Asha.', $preview['data']['sample_message']);
        $this->assertSame('******3210', $preview['data']['recipients'][0]['contact_masked']);
        $this->assertSame(0, $service->smsCalls + $service->whatsappCalls);
        $this->assertStringNotContainsString('9876543210', json_encode($preview));
        $this->assertStringNotContainsString('SECRET', json_encode($preview));
    }

    public function test_a_number_that_is_not_a_valid_mobile_is_not_contacted(): void
    {
        $data = $this->service()->preview($this->context(), $this->sms())['data'];

        $this->assertSame('The mobile number on record is not a valid 10-digit Indian mobile.', $data['unreachable'][0]['reason']);
    }

    public function test_nothing_is_sent_without_an_approved_fingerprint(): void
    {
        $service = $this->service();
        $result = $service->send($this->context(), $this->sms(), null);

        $this->assertFalse($result['success']);
        $this->assertSame('no_approved_list', $result['error']['code']);
        $this->assertSame(0, $service->smsCalls);
    }

    public function test_a_changed_message_invalidates_the_approval(): void
    {
        $service = $this->service();
        $fingerprint = $this->approved($service, $this->sms());

        $result = $service->send($this->context(), $this->sms('A different message.'), $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $service->smsCalls);
    }

    public function test_an_accepted_sms_is_reported_as_accepted_and_logged(): void
    {
        $service = $this->service(['smsReply' => ['error' => 0, 'http' => 200, 'body' => 'Message accepted id=991']]);
        $result = $service->send($this->context(), $this->sms(), $this->approved($service, $this->sms()));

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['sent']);
        $this->assertSame(2, $service->smsCalls);
        $this->assertCount(2, $service->legacyLog);
        $this->assertStringContainsString('delivery to the phone is not reported back', $result['message']);
    }

    /** @return array<string, array{0:array<string, mixed>}> */
    public static function refusals(): array
    {
        return [
            'body says invalid' => [['error' => 0, 'http' => 200, 'body' => 'Invalid sender id']],
            'body says insufficient credits' => [['error' => 0, 'http' => 200, 'body' => 'Insufficient balance']],
            'empty body' => [['error' => 0, 'http' => 200, 'body' => '']],
            'http 500' => [['error' => 0, 'http' => 500, 'body' => 'oops']],
            'redirect' => [['error' => 0, 'http' => 302, 'body' => '', 'redirect_host' => 'elsewhere.example']],
            'curl error' => [['error' => 1, 'message' => 'Could not resolve host-6']],
            'no reply read' => [['error' => 0]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function test_a_refused_sms_is_never_reported_as_sent(array $reply): void
    {
        $service = $this->service(['smsReply' => $reply]);
        $result = $service->send($this->context(), $this->sms(), $this->approved($service, $this->sms()));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['error']['details']['sent']);
        $this->assertSame([], $service->legacyLog);
    }

    public function test_a_whatsapp_message_is_sent_only_when_a_message_id_comes_back(): void
    {
        $arguments = ['channel' => 'whatsapp', 'message' => 'Hello {name}', 'student_ids' => [1, 2, 3]];
        $service = $this->service(['whatsappReply' => ['messages' => [['id' => 'wamid.HBgM123']]]]);
        $result = $service->send($this->context(), $arguments, $this->approved($service, $arguments));

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['sent']);
        $this->assertSame('wamid.HBgM123', $result['data']['results'][0]['provider_id']);
        // The last number called, with the country code the Communication screen adds.
        $this->assertSame('919876543211', $service->lastWhatsappTo);
        $this->assertSame('whatsapp-log', $service->whatsappLog[0]['kind']);
    }

    /** @return array<string, array{0:array<string, mixed>}> */
    public static function whatsappRefusals(): array
    {
        return [
            'api error' => [['error' => ['message' => 'Template not approved', 'code' => 132001]]],
            'per-message error' => [['messages' => [['error' => ['message' => 'blocked']]]]],
            'no id and no error' => [['messaging_product' => 'whatsapp']],
            'empty' => [[]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('whatsappRefusals')]
    public function test_a_refused_whatsapp_message_is_never_reported_as_sent(array $reply): void
    {
        $arguments = ['channel' => 'whatsapp', 'message' => 'Hello', 'student_ids' => [1, 2, 3]];
        $service = $this->service(['whatsappReply' => $reply]);
        $result = $service->send($this->context(), $arguments, $this->approved($service, $arguments));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['error']['details']['sent']);
    }

    public function test_a_channel_that_is_not_configured_is_refused_with_what_is_missing(): void
    {
        $sms = $this->service(['hasSms' => false])->preview($this->context(), $this->sms());
        $wa = $this->service(['hasWhatsapp' => false])->preview($this->context(), ['channel' => 'whatsapp', 'message' => 'Hi', 'student_ids' => [1]]);

        $this->assertFalse($sms['success']);
        $this->assertStringContainsString('SMS API', $sms['message']);
        $this->assertFalse($wa['success']);
        $this->assertStringContainsString('access token and phone number id', $wa['message']);
    }

    public function test_the_request_is_validated_before_anything_else(): void
    {
        $service = $this->service();
        $cases = [
            ['channel' => 'telegram', 'message' => 'x', 'student_ids' => [1]],
            ['channel' => 'sms', 'message' => '   ', 'student_ids' => [1]],
            ['channel' => 'sms', 'message' => str_repeat('a', 481), 'student_ids' => [1]],
            ['channel' => 'sms', 'message' => 'hello'],
        ];

        foreach ($cases as $case) {
            $this->assertFalse($service->preview($this->context(), $case)['success'], json_encode($case));
        }

        $this->assertSame(0, $service->smsCalls);
    }

    public function test_the_same_message_is_not_sent_twice_to_the_same_student(): void
    {
        $service = $this->service(['smsReply' => ['error' => 0, 'http' => 200, 'body' => 'accepted'], 'alreadySent' => true]);
        $result = $service->send($this->context(), $this->sms(), $this->approved($service, $this->sms()));

        $this->assertFalse($result['success']);
        $this->assertSame(0, $service->smsCalls);
        $this->assertSame(2, $result['error']['details']['skipped']);
    }

    public function test_two_gateway_outages_stop_the_rest_instead_of_waiting_on_each(): void
    {
        $service = $this->service(['smsReply' => ['error' => 1, 'message' => 'timeout-28'], 'extra' => 5]);
        $arguments = $this->sms();
        $result = $service->send($this->context(), $arguments, $this->approved($service, $arguments));

        $this->assertFalse($result['success']);
        $this->assertSame(2, $service->smsCalls);
        $this->assertSame(7, $result['error']['details']['failed']);
    }

    public function test_variables_are_filled_from_each_students_own_record(): void
    {
        $service = $this->service(['smsReply' => ['error' => 0, 'http' => 200, 'body' => 'accepted']]);
        $arguments = $this->sms('Dear parent of {student_name} ({class}, GR {enrollment_no}): hi {name}.');

        $preview = $service->preview($this->context(), $arguments)['data'];
        $service->send($this->context(), $arguments, $preview['fingerprint']);

        $this->assertSame('Dear parent of Asha Shah (5 A, GR GR101): hi Asha.', $preview['sample_message']);
        $this->assertSame('Dear parent of Ravi Patel (5 A, GR GR102): hi Ravi.', $service->sentTexts[1]);
        $this->assertArrayHasKey('{enrollment_no}', $preview['variables']);
    }

    public function test_a_saved_sms_signature_is_shown_and_appended_exactly_like_the_parent_module(): void
    {
        $service = $this->service(['lastVar' => '.(Generated at CMA)', 'smsReply' => ['error' => 0, 'http' => 200, 'body' => 'accepted']]);
        $preview = $service->preview($this->context(), $this->sms('Closed tomorrow'))['data'];

        $this->assertSame('signature', $preview['template']['kind']);
        $this->assertSame('.(Generated at CMA)', $preview['template']['text']);
        $this->assertSame('Closed tomorrow.(Generated at CMA)', $preview['final_message']);

        $service->send($this->context(), $this->sms('Closed tomorrow'), $preview['fingerprint']);

        $this->assertSame('Closed tomorrow.(Generated at CMA)', $service->sentTexts[0]);
    }

    public function test_a_gateway_template_parameter_is_shown_masked_and_not_added_to_the_text(): void
    {
        $service = $this->service(['lastVar' => '&templateid=1707166555566064422&password=hunter2']);
        $data = $service->preview($this->context(), $this->sms('Closed tomorrow'))['data'];

        $this->assertSame('gateway_parameter', $data['template']['kind']);
        $this->assertStringContainsString('templateid=1707166555566064422', $data['template']['name']);
        $this->assertStringNotContainsString('hunter2', json_encode($data));
        $this->assertSame('Closed tomorrow', $data['final_message']);
    }

    public function test_changing_the_saved_template_after_approval_invalidates_it(): void
    {
        $service = $this->service(['lastVar' => '.Old signature']);
        $fingerprint = $this->approved($service, $this->sms());

        $service->lastVar = '.New signature';
        $result = $service->send($this->context(), $this->sms(), $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame('recipients_changed', $result['error']['code']);
        $this->assertSame(0, $service->smsCalls);
    }

    public function test_whatsapp_text_is_one_line_and_names_its_template(): void
    {
        $arguments = ['channel' => 'whatsapp', 'message' => "Line one\n\nLine two   spaced", 'student_ids' => [1, 2, 3]];
        $service = $this->service(['whatsappReply' => ['messages' => [['id' => 'wamid.X']]]]);

        $preview = $service->preview($this->context(), $arguments)['data'];
        $service->send($this->context(), $arguments, $preview['fingerprint']);

        $this->assertSame('cloud_template', $preview['template']['kind']);
        $this->assertSame('parents_', $preview['template']['name']);
        $this->assertSame('Line one Line two spaced', $service->sentTexts[0]);

        $numbered = $service->preview($this->context(), ['channel' => 'whatsapp', 'message' => 'A {{1}} B {{2}}', 'student_ids' => [1]])['data'];
        $this->assertSame('p2', $numbered['template']['name']);
    }

    public function test_describe_shows_the_selected_student_the_template_and_the_variables_without_a_message(): void
    {
        $service = $this->service(['lastVar' => '.Sig']);
        $result = $service->describe($this->context(), ['channel' => 'sms', 'student_ids' => [1, 2, 3]]);

        $this->assertTrue($result['success']);
        $data = $result['data'];
        $this->assertSame('Asha Shah', $data['students'][0]['name']);
        $this->assertSame('GR101', $data['students'][0]['enrollment_no']);
        $this->assertSame('******3210', $data['students'][0]['contact_masked']);
        $this->assertNotEmpty($data['students'][2]['problem']);
        $this->assertSame('.Sig', $data['template']['text']);
        $this->assertSame(480, $data['limit']);
        $this->assertSame(0, $service->smsCalls + $service->whatsappCalls);
        $this->assertStringNotContainsString('SECRET', json_encode($result));
    }

    /** @param  array<string, mixed>  $result */
    private function ledgerRow(string $status, string $channel, array $result = []): object
    {
        return (object) [
            // Two minutes ago: recent enough that a WhatsApp message with no receipt yet is still just "pending".
            'id' => 5, 'subject_id' => 12, 'outcome' => $status === 'completed' ? 'success' : 'failure', 'created_at' => \Carbon\Carbon::now()->subMinutes(2)->toDateTimeString(), 'actor_id' => 3,
            'payload' => json_encode(['status' => $status, 'reference' => 'PM-20261015-abcde', 'subject_label' => 'ASHA RAO',
                'result' => $result + ['channel' => $channel, 'message' => 'Hello', 'detail' => 'Accepted.', 'sent_to' => '******3210', 'provider_id' => 'wamid.X']]),
        ];
    }

    public function test_a_saved_message_reads_back_with_an_honest_delivery_state(): void
    {
        $service = $this->service();
        $read = fn (object $row) => (fn () => $this->messageFromRow($row))->call($service);

        // SMS accepted: unknown, never "delivered".
        $sms = $read($this->ledgerRow('completed', 'sms', ['provider_id' => '9223178032411384']));
        $this->assertSame('sent', $sms['status']);
        $this->assertSame('unknown', $sms['delivery_status']);
        $this->assertStringContainsString('delivery report is not available', $sms['delivery_note']);
        $this->assertSame('9223178032411384', $sms['provider_id']);
        $this->assertSame('PM-20261015-abcde', $sms['batch']);
        $this->assertSame('Hello', $sms['message']);

        $this->assertSame('not_delivered', $read($this->ledgerRow('failed', 'sms'))['delivery_status']);
        $this->assertSame('not_sent', $read($this->ledgerRow('skipped', 'whatsapp'))['delivery_status']);
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function whatsappReceipts(): array
    {
        return [
            'read' => ['read', 'read'],
            'delivered' => ['delivered', 'delivered'],
            'delivered with a code' => ['delivered ()', 'delivered'],
            'failed later' => ['failed (131026)', 'failed'],
            'only sent' => ['sent', 'pending'],
            'nothing yet' => ['', 'pending'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('whatsappReceipts')]
    public function test_a_whatsapp_message_shows_what_whatsapp_has_reported_since(string $reported, string $expected): void
    {
        $service = $this->service(['reported' => $reported]);
        $row = $this->ledgerRow('completed', 'whatsapp');

        // The real mapping runs; only the read of WhatsApp's recorded status is replaced.
        $this->assertSame($expected, (fn () => $this->messageFromRow($row))->call($service)['delivery_status']);
    }

    public function test_an_old_whatsapp_message_with_no_receipt_is_not_confirmed_and_says_why(): void
    {
        $service = $this->service(['reported' => '']);
        $row = $this->ledgerRow('completed', 'whatsapp');
        $row->created_at = \Carbon\Carbon::now()->subHours(2)->toDateTimeString();

        [$state, $note] = [(fn () => $this->messageFromRow($row))->call($service)['delivery_status'], (fn () => $this->messageFromRow($row))->call($service)['delivery_note']];

        $this->assertSame('not_confirmed', $state);
        $this->assertStringContainsString('/api/whatsapp/webhook', $note);
    }

    public function test_describe_refuses_an_unconfigured_channel_before_any_wording_is_typed(): void
    {
        $result = $this->service(['hasWhatsapp' => false])->describe($this->context(), ['channel' => 'whatsapp', 'student_ids' => [1]]);

        $this->assertFalse($result['success']);
        $this->assertSame('channel_not_configured', $result['error']['code']);
    }
}

/**
 * The service with its senders, settings and students replaced.
 */
class FakeParentMessageService extends ParentMessageService
{
    public int $smsCalls = 0;
    public int $whatsappCalls = 0;
    public ?string $lastWhatsappTo = null;
    public array $smsReply = ['error' => 0, 'http' => 200, 'body' => 'accepted'];
    public array $whatsappReply = [];
    public array $legacyLog = [];
    public array $whatsappLog = [];
    public bool $hasSms = true;
    public bool $hasWhatsapp = true;
    public bool $alreadySent = false;
    public int $extra = 0;
    public string $lastVar = '';
    public array $sentTexts = [];

    protected function students(McpRequestContext $context, array $arguments): ?array
    {
        if (empty($arguments['student_ids']) && empty($arguments['standard_id'])) {
            return null;
        }

        $rows = [
            ['student_id' => 1, 'name' => 'Asha Shah', 'first_name' => 'Asha', 'class' => '5 A', 'enrollment_no' => 'GR101', 'mobile' => '9876543210', 'standard_id' => null, 'division_id' => null],
            ['student_id' => 2, 'name' => 'Ravi Patel', 'first_name' => 'Ravi', 'class' => '5 A', 'enrollment_no' => 'GR102', 'mobile' => '+91 98765 43211', 'standard_id' => null, 'division_id' => null],
            ['student_id' => 3, 'name' => 'Meena Joshi', 'first_name' => 'Meena', 'class' => '5 A', 'enrollment_no' => 'GR103', 'mobile' => '12345', 'standard_id' => null, 'division_id' => null],
        ];

        for ($i = 0; $i < $this->extra; $i++) {
            $rows[] = ['student_id' => 100 + $i, 'name' => 'Extra '.$i, 'first_name' => 'E'.$i, 'class' => '5 B', 'enrollment_no' => 'GR9'.$i, 'mobile' => '98765'.str_pad((string) (10000 + $i), 5, '0'), 'standard_id' => null, 'division_id' => null];
        }

        return $rows;
    }

    protected function smsGateway(McpRequestContext $context): ?array
    {
        return $this->hasSms ? ['url' => 'https://gateway.example/send?key=SECRET', 'sub_institute_id' => 1, 'last_var' => $this->lastVar] : null;
    }

    protected function whatsappConfiguration(McpRequestContext $context): ?array
    {
        return $this->hasWhatsapp ? ['cloud_api_access_token' => 'SECRET-TOKEN', 'cloud_api_phone_number_id' => '111'] : null;
    }

    protected function sendSmsRequest(int $instituteId, string $mobile, string $text): array
    {
        $this->smsCalls++;
        $this->sentTexts[] = $text;

        return $this->smsReply;
    }

    protected function sendWhatsappRequest(string $to, string $text, string $accessToken, string $phoneNumberId): array
    {
        $this->whatsappCalls++;
        $this->lastWhatsappTo = $to;
        $this->sentTexts[] = $text;

        return $this->whatsappReply;
    }

    protected function logLegacySms(int $studentId, string $text, string $mobile, McpRequestContext $context): void
    {
        $this->legacyLog[] = [$studentId, $mobile];
    }

    protected function logWhatsapp(McpRequestContext $context, array $recipient, string $text, string $status, ?string $error, ?string $providerId): void
    {
        $this->whatsappLog[] = ['kind' => 'whatsapp-log', 'status' => $status, 'id' => $providerId];
    }

    protected function recentlySentAt(McpRequestContext $context, int $studentId, string $channel, string $text): ?string
    {
        return $this->alreadySent ? '10:00' : null;
    }

    /** What WhatsApp's delivery webhook has written for the message, as `message_status`. */
    public string $reported = '';

    protected function reportedWhatsappStatus(string $providerId): ?string
    {
        return $this->reported === '' ? null : $this->reported;
    }
}
