<?php

namespace Tests\Unit;

use App\Domain\AI\Support\AiAuditLogger;
use App\Mail\FeeReminderNotice;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\FeesPositionService;
use App\Services\Mcp\FeesReminderService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ToolResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fee payment reminders: who is told what, by which channel, and that nothing leaves without the
 * approved list.
 *
 * No database is touched and nothing real leaves. The fee ledger, the student contacts, the SMS
 * gateway row, the legacy SMS log and the audit ledger are replaced with fixtures; mail is faked
 * and HTTP is faked. A test that wrote "sent" rows about real students into the live ledger, or
 * texted a real parent, would be a lie told to the people it describes.
 */
class FeesReminderServiceTest extends TestCase
{
    private const GATEWAY = [
        'url' => 'https://sms.example.test/send?',
        'pram' => 'user=SECRETUSER&pass=SECRETPASS&sender=SCHOOL',
        'mobile_var' => '&to=',
        'text_var' => '&message=',
        'last_var' => '&key=SECRETKEY',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        Mail::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  array<int, string|null>  $emails  student id => address
     * @param  array<int, string|null>  $mobiles  student id => number
     * @return array{0: FeesReminderService, 1: object, 2: object}
     */
    private function service(array $accounts, array $emails, array $mobiles = [], ?array $gateway = self::GATEWAY): array
    {
        $position = new class($accounts) extends FeesPositionService
        {
            public function __construct(public array $accounts)
            {
            }

            public function accounts(McpRequestContext $context, array $arguments): array
            {
                return ToolResult::success('fees.outstanding_accounts', 'ok', ['accounts' => $this->accounts]);
            }
        };

        $audit = new class extends AiAuditLogger
        {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function __construct()
            {
            }

            public function record(string $eventType, ?McpRequestContext $context = null, array $options = []): ?int
            {
                $this->rows[] = ['event' => $eventType] + $options;

                return count($this->rows);
            }
        };

        $service = new class($position, $audit, $emails, $mobiles, $gateway) extends FeesReminderService
        {
            /** @var array<int, array<string, mixed>> */
            public array $legacyLog = [];

            /** @var array<int, array<string, mixed>> What was handed to the Communication sender. */
            public array $handed = [];

            public ?\Closure $reply = null;

            /** @var array<int, string> student id => when a matching reminder was last accepted */
            public array $recent = [];

            protected function recentlySentAt(McpRequestContext $context, int $studentId, string $channel, \Illuminate\Support\Carbon $deadline): ?string
            {
                return $this->recent[$studentId] ?? null;
            }

            public function __construct($position, $audit, private readonly array $emails, private readonly array $mobiles, private readonly ?array $gateway)
            {
                parent::__construct($position, $audit);
            }

            protected function contactsFor(McpRequestContext $context, array $studentIds): array
            {
                $contacts = [];

                foreach ($studentIds as $id) {
                    $contacts[$id] = ['email' => $this->emails[$id] ?? null, 'mobile' => $this->mobiles[$id] ?? null];
                }

                return $contacts;
            }

            protected function smsGateway(McpRequestContext $context): ?array
            {
                return $this->gateway;
            }

            protected function legacySend(int $instituteId, string $mobile, string $text): array
            {
                $this->handed[] = ['institute' => $instituteId, 'mobile' => $mobile, 'text' => $text];

                return $this->reply === null ? ['error' => 0] : ($this->reply)();
            }

            protected function logLegacySms(int $studentId, string $text, string $mobile, McpRequestContext $context): void
            {
                $this->legacyLog[] = compact('studentId', 'text', 'mobile');
            }

            // ---- WhatsApp: the institute's credentials, the Cloud API call, its log and its delivery receipts, all replaced.

            /** @var array<string, string>|null null = WhatsApp is not set up for the institute. */
            public ?array $whatsapp = ['cloud_api_access_token' => 'SECRET-TOKEN', 'cloud_api_phone_number_id' => '111'];

            public ?\Closure $whatsappReply = null;

            /** @var array<int, array<string, string>> */
            public array $whatsappCalls = [];

            /** @var array<int, array<string, mixed>> */
            public array $whatsappLog = [];

            public ?string $whatsappReported = null;

            protected function whatsappConfiguration(McpRequestContext $context): ?array
            {
                return $this->whatsapp;
            }

            protected function sendWhatsappRequest(string $to, string $text, string $accessToken, string $phoneNumberId): array
            {
                $this->whatsappCalls[] = compact('to', 'text');

                return $this->whatsappReply === null ? ['messages' => [['id' => 'wamid.HBgM123']]] : ($this->whatsappReply)();
            }

            protected function logWhatsapp(McpRequestContext $context, array $recipient, string $text, string $status, ?string $error, ?string $providerId): void
            {
                $this->whatsappLog[] = compact('status', 'error', 'providerId');
            }

            protected function reportedWhatsappStatus(string $providerId): ?string
            {
                return $this->whatsappReported;
            }
        };

        return [$service, $audit, $service];
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 61, [61], null, null, 2026, null, true, false);
    }

    private function account(int $id, string $name, float $owes, string $class = 'IX'): array
    {
        return ['student_id' => $id, 'student_name' => $name, 'standard_name' => $class, 'outstanding' => $owes];
    }

    /** @return array<int, array<string, mixed>> */
    private function ledger(): array
    {
        return [
            $this->account(1, 'ASHA RAO', 335480),
            $this->account(2, 'BINA SHAH', 271440),
            $this->account(3, 'CHIRAG DESAI', 120000),
            $this->account(4, 'DIVYA IYER', 4500),
        ];
    }

    private function emails(): array
    {
        return [1 => 'asha@example.com', 2 => null, 3 => 'not-an-email', 4 => 'divya@example.com'];
    }

    private function mobiles(): array
    {
        return [1 => '9876543210', 2 => null, 3 => '12345', 4 => '+91 98765 43211'];
    }

    // --------------------------------------------------------------- preview

    public function test_the_preview_names_who_will_and_will_not_be_contacted_and_sends_nothing(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());

        $preview = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 4]);

        $this->assertTrue($preview['success']);
        $data = $preview['data'];

        $this->assertSame('email', $data['channel']);
        $this->assertSame(2, $data['recipient_count']);
        $this->assertSame(2, $data['unreachable_count']);
        $this->assertSame(['ASHA RAO', 'DIVYA IYER'], array_column($data['recipients'], 'name'));
        $this->assertSame(['BINA SHAH', 'CHIRAG DESAI'], array_column($data['unreachable'], 'name'));
        $this->assertSame('No email address on the student record.', $data['unreachable'][0]['reason']);
        $this->assertSame('The email address on record is not valid.', $data['unreachable'][1]['reason']);

        // Addresses are masked in what is shown, and nothing was sent.
        $this->assertSame('a***@example.com', $data['recipients'][0]['contact_masked']);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_the_sample_is_the_exact_wording_with_the_students_own_name_amount_and_date(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31'])['data'];

        $this->assertStringContainsString('Dear ASHA RAO,', $data['sample_message']);
        $this->assertStringContainsString(
            'Your outstanding fee amount is ₹3,35,480. Please submit your pending fees by 31 October 2026.',
            $data['sample_message']
        );
        $this->assertSame('Fee payment reminder', $data['sample_subject']);
        $this->assertSame('31 October 2026', $data['deadline_label']);
        $this->assertEqualsCanonicalizing(339980.0, $data['total_outstanding']);
    }

    public function test_a_deadline_that_is_missing_past_impossible_or_too_far_off_is_refused(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());

        foreach (['', '2026-10-12', '2026-02-30', 'soon', '31/10/2026', '2028-01-01'] as $deadline) {
            $result = $service->preview($this->scope(), ['deadline' => $deadline]);

            $this->assertFalse($result['success'], "deadline '{$deadline}' was accepted");
            $this->assertSame('invalid_deadline', $result['error']['code']);
        }

        // Today is a valid deadline; yesterday is not.
        $this->assertTrue($service->preview($this->scope(), ['deadline' => '2026-10-13'])['success']);
    }

    public function test_the_minimum_amount_and_the_count_narrow_the_list_and_it_is_capped(): void
    {
        $many = [];
        foreach (range(1, 40) as $i) {
            $many[] = $this->account($i, 'STUDENT '.$i, 100000 - $i);
        }

        [$service] = $this->service($many, array_fill_keys(range(1, 40), 'x@example.com'));
        $capped = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 999])['data'];
        $this->assertSame(FeesReminderService::MAX_RECIPIENTS, $capped['recipient_count']);

        [$service] = $this->service($this->ledger(), $this->emails());
        $narrow = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'min_outstanding' => 200000]);
        $this->assertSame(1, $narrow['data']['recipient_count'] + $narrow['data']['unreachable_count'] - 1);
        $this->assertSame('nobody_owes', $service->preview($this->scope(), ['deadline' => '2026-10-31', 'min_outstanding' => 9e9])['error']['code']);
    }

    public function test_a_student_who_owes_nothing_is_never_a_recipient(): void
    {
        [$service] = $this->service([$this->account(1, 'PAID UP', 0), $this->account(2, 'OWES', 500)], [1 => 'a@example.com', 2 => 'b@example.com']);

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31'])['data'];

        $this->assertSame(['OWES'], array_column($data['recipients'], 'name'));
    }

    // ------------------------------------------------------------------ send

    public function test_nothing_is_sent_without_the_fingerprint_of_an_approved_list(): void
    {
        [$service, $audit] = $this->service($this->ledger(), $this->emails());

        foreach ([null, ''] as $fingerprint) {
            $result = $service->send($this->scope(), ['deadline' => '2026-10-31'], $fingerprint);

            $this->assertFalse($result['success']);
            $this->assertSame('no_approved_list', $result['error']['code']);
        }

        Mail::assertNothingSent();
        $this->assertSame([], $audit->rows);
    }

    public function test_it_sends_only_the_approved_list_and_records_every_students_outcome(): void
    {
        [$service, $audit] = $this->service($this->ledger(), $this->emails());
        $args = ['deadline' => '2026-10-31', 'top' => 4];

        $fingerprint = $service->preview($this->scope(), $args)['data']['fingerprint'];
        $result = $service->send($this->scope(), $args, $fingerprint);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['sent']);
        $this->assertSame(0, $result['data']['failed']);
        $this->assertSame(2, $result['data']['skipped']);

        // Exactly the two reachable students were emailed, each only their own figures.
        Mail::assertSent(FeeReminderNotice::class, 2);
        Mail::assertSent(FeeReminderNotice::class, fn ($mail) => $mail->hasTo('asha@example.com')
            && str_contains($mail->messageHtml, '₹3,35,480') && ! str_contains($mail->messageHtml, '4,500'));
        Mail::assertSent(FeeReminderNotice::class, fn ($mail) => $mail->hasTo('divya@example.com')
            && str_contains($mail->messageHtml, '₹4,500') && ! str_contains($mail->messageHtml, '3,35,480'));

        // One ledger row per student, including the two who were not contacted.
        $this->assertCount(4, $audit->rows);
        $byStudent = [];
        foreach ($audit->rows as $row) {
            $this->assertSame(FeesReminderService::EVENT, $row['event']);
            $byStudent[$row['subject_id']] = $row['payload']['status'];
        }
        // Ledger vocabulary is the Activity tab's: completed (accepted by the mail server) / skipped.
        $this->assertSame([1 => 'completed', 2 => 'skipped', 3 => 'skipped', 4 => 'completed'], $byStudent);

        // Addresses are stored masked, never whole.
        foreach ($audit->rows as $row) {
            $this->assertStringNotContainsString('asha@example.com', json_encode($row));
        }
    }

    public function test_a_list_that_changed_since_it_was_approved_is_refused_and_nothing_goes_out(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());
        $args = ['deadline' => '2026-10-31', 'top' => 4];
        $approved = $service->preview($this->scope(), $args)['data']['fingerprint'];

        // Somebody pays between the preview and the "yes".
        $ledger = $this->ledger();
        $ledger[0]['outstanding'] = 100;
        [$later, $audit] = $this->service($ledger, $this->emails());

        $result = $later->send($this->scope(), $args, $approved);

        $this->assertFalse($result['success']);
        $this->assertSame('recipients_changed', $result['error']['code']);
        Mail::assertNothingSent();
        $this->assertSame([], $audit->rows);

        // A different deadline is a different approval too.
        $other = $service->send($this->scope(), ['deadline' => '2026-11-15', 'top' => 4], $approved);
        $this->assertSame('recipients_changed', $other['error']['code']);
        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_is_recorded_as_failed_and_never_as_sent(): void
    {
        [$service, $audit] = $this->service($this->ledger(), $this->emails());
        $args = ['deadline' => '2026-10-31', 'top' => 4];
        $fingerprint = $service->preview($this->scope(), $args)['data']['fingerprint'];

        Mail::shouldReceive('to')->andReturnUsing(function (string $address) {
            return new class($address)
            {
                public function __construct(private readonly string $address)
                {
                }

                public function send($mailable): void
                {
                    if ($this->address === 'asha@example.com') {
                        throw new \RuntimeException('Connection could not be established with host smtp.example.com');
                    }
                }
            };
        });

        $result = $service->send($this->scope(), $args, $fingerprint);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['data']['sent']);
        $this->assertSame(1, $result['data']['failed']);

        $byStudent = [];
        foreach ($audit->rows as $row) {
            $byStudent[$row['subject_id']] = [$row['payload']['status'], $row['outcome']];
        }
        $this->assertSame(['failed', 'failure'], $byStudent[1]);
        $this->assertSame(['completed', 'success'], $byStudent[4]);

        $failed = array_values(array_filter($result['data']['results'], fn ($r) => $r['status'] === 'failed'))[0];
        $this->assertStringContainsString('Connection could not be established', $failed['detail']);
    }

    public function test_when_nothing_at_all_is_accepted_the_action_reports_failure_not_success(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());
        $args = ['deadline' => '2026-10-31', 'top' => 4];
        $fingerprint = $service->preview($this->scope(), $args)['data']['fingerprint'];

        Mail::shouldReceive('to')->andReturnUsing(fn () => new class
        {
            public function send($mailable): void
            {
                throw new \RuntimeException('refused');
            }
        });

        $result = $service->send($this->scope(), $args, $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame('send_failed', $result['error']['code']);
        $this->assertSame(2, $result['error']['details']['failed']);
    }

    public function test_when_nobody_has_an_email_there_is_nothing_to_send(): void
    {
        [$service] = $this->service([$this->account(1, 'NO MAIL', 900)], [1 => null]);
        $args = ['deadline' => '2026-10-31'];

        $preview = $service->preview($this->scope(), $args)['data'];
        $this->assertSame(0, $preview['recipient_count']);
        $this->assertNull($preview['sample_message']);

        $result = $service->send($this->scope(), $args, $preview['fingerprint']);
        $this->assertSame('no_recipients', $result['error']['code']);
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------- SMS

    public function test_an_sms_preview_shows_numbers_masked_the_exact_text_its_length_and_the_dlt_warning(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'])['data'];

        $this->assertSame('sms', $data['channel']);
        $this->assertSame(['ASHA RAO', 'DIVYA IYER'], array_column($data['recipients'], 'name'));
        // "+91 98765 43211" is a valid number written another way; "12345" and none are not.
        $this->assertSame('******3210', $data['recipients'][0]['contact_masked']);
        $this->assertSame('******3211', $data['recipients'][1]['contact_masked']);
        $this->assertSame('No mobile number on the student record.', $data['unreachable'][0]['reason']);
        $this->assertSame('The mobile number on record is not a valid 10-digit Indian mobile.', $data['unreachable'][1]['reason']);

        // Rs., not the rupee sign, which would make the message Unicode and halve its length.
        $this->assertSame(
            'Dear ASHA RAO, your outstanding fee amount is Rs.3,35,480. Please submit your pending fees by 31 October 2026.',
            $data['sample_message']
        );
        $this->assertFalse($data['sms']['unicode']);
        $this->assertSame(1, $data['sms']['segments']);
        $this->assertSame('sms.example.test', $data['sms']['gateway_host']);
        $this->assertNull($data['sample_subject']);
        $this->assertStringContainsString('DLT', implode(' ', $data['warnings']));

        // The preview never carries the gateway's credentials.
        $this->assertStringNotContainsString('SECRET', json_encode($data));
        Http::assertNothingSent();
    }

    public function test_sms_is_refused_up_front_when_the_institute_has_no_active_gateway(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails(), $this->mobiles(), null);

        $result = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'channel' => 'sms']);

        $this->assertFalse($result['success']);
        $this->assertSame('sms_not_configured', $result['error']['code']);
        $this->assertStringContainsString('Manage SMS API', $result['message']);

        // Email is unaffected.
        $this->assertTrue($service->preview($this->scope(), ['deadline' => '2026-10-31', 'channel' => 'email'])['success']);
    }

    public function test_an_sms_goes_through_the_communication_modules_own_sender_and_is_logged_and_recorded(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $args = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'];
        $fingerprint = $service->preview($this->scope(), $args)['data']['fingerprint'];

        $result = $service->send($this->scope(), $args, $fingerprint);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['sent']);
        $this->assertSame('sms', $result['data']['channel']);

        // The sender is the one Send SMS to Parents uses, called with this student's own institute,
        // number and text — and nothing else went out over HTTP.
        $this->assertCount(2, $service->handed);
        $this->assertSame(61, $service->handed[0]['institute']);
        $this->assertSame('9876543210', $service->handed[0]['mobile']);
        $this->assertStringContainsString('Rs.3,35,480', $service->handed[0]['text']);
        Http::assertNothingSent();

        // It does not read the provider's reply, and the result says so rather than claiming acceptance.
        $detail = $result['data']['results'][0]['detail'];
        $this->assertStringContainsString('Handed to the SMS gateway', $detail);
        $this->assertStringContainsString('not confirmed', $detail);

        // The Communication log is written for each message handed over, and only those.
        $this->assertSame([1, 4], array_column($service->legacyLog, 'studentId'));

        $first = $audit->rows[0];
        $this->assertSame('sms', $first['payload']['result']['channel']);
        $this->assertSame('******3210', $first['payload']['result']['sent_to']);
        foreach ($audit->rows as $row) {
            $this->assertStringNotContainsString('9876543210', json_encode($row));
        }
    }

    public function test_a_reply_the_provider_accepted_is_recorded_with_its_own_words_and_the_delivery_caveat(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $service->reply = static fn () => ['error' => 0, 'http' => 200, 'body' => 'Message accepted, id=88123', 'redirect_host' => null];
        $args = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        $this->assertTrue($result['success']);
        $detail = $result['data']['results'][0]['detail'];
        $this->assertStringContainsString('Accepted by the SMS gateway (HTTP 200): Message accepted, id=88123', $detail);
        $this->assertStringContainsString('Delivery to the phone is not reported back', $detail);
        $this->assertStringContainsString('accepted by the SMS gateway', $result['message'] . ' ' . ($result['data']['summary'] ?? ''));
        $this->assertSame([1, 4], array_column($service->legacyLog, 'studentId'));
        $this->assertSame('completed', $audit->rows[0]['payload']['status']);
    }

    public function test_a_provider_error_body_is_a_failure_even_though_the_request_went_through(): void
    {
        foreach (['{"status":"FAILURE","message":"Invalid API Key...!","data":null}', 'Error: template not approved', 'Insufficient balance'] as $body) {
            [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
            $service->reply = static fn () => ['error' => 0, 'http' => 200, 'body' => $body, 'redirect_host' => null];
            $args = ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'];

            $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

            $this->assertFalse($result['success'], $body);
            $this->assertStringContainsString('replied with an error', $result['error']['details']['results'][0]['detail']);
            $this->assertSame([], $service->legacyLog, 'a refused message is never written to the Communication log');
            $this->assertSame('failed', $audit->rows[0]['payload']['status']);
        }
    }

    public function test_a_redirect_is_a_refusal_that_names_the_new_host_but_not_the_key(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $service->reply = static fn () => ['error' => 0, 'http' => 302, 'body' => '', 'redirect_host' => 'static.provider.example'];
        $args = ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        $this->assertFalse($result['success']);
        $detail = $result['error']['details']['results'][0]['detail'];
        $this->assertStringContainsString('redirected', $detail);
        $this->assertStringContainsString('static.provider.example', $detail);
        $this->assertStringContainsString('SMS settings', $detail);
        $this->assertStringNotContainsString('SECRET', json_encode([$result, $audit->rows]));
        $this->assertStringNotContainsString('sms-oops', $detail);
    }

    public function test_an_empty_reply_and_a_server_error_are_failures_not_acceptances(): void
    {
        foreach ([['http' => 200, 'body' => ''], ['http' => 503, 'body' => 'Service unavailable'], ['http' => 400, 'body' => 'Bad request']] as $reply) {
            [$service] = $this->service($this->ledger(), [], $this->mobiles());
            $service->reply = static fn () => ['error' => 0, 'redirect_host' => null] + $reply;
            $args = ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'];

            $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

            $this->assertFalse($result['success'], json_encode($reply));
        }
    }

    public function test_the_same_reminder_just_accepted_is_not_sent_to_the_same_person_again(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        // Student 1 was sent this exact reminder three minutes ago (a repeated Approve, a retried request).
        $service->recent = [1 => '2026-10-14 12:16:30'];
        $args = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        // Only student 4 was handed to the gateway; student 1 is recorded as skipped, with the reason.
        $this->assertSame([4], array_column($service->legacyLog, 'studentId'));
        $this->assertCount(1, $service->handed);
        $first = array_values(array_filter($result['data']['results'], fn ($r) => $r['student_id'] === 1))[0];
        $this->assertSame('skipped', $first['status']);
        $this->assertStringContainsString('Already sent at 2026-10-14 12:16:30', $first['detail']);
    }

    public function test_an_error_from_the_sender_is_a_failure_with_its_reason_and_is_not_logged_as_sent(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $service->reply = static fn () => ['error' => 1, 'message' => 'Please add api details first.'];
        $args = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['error']['details']['sent']);
        $this->assertStringContainsString('Please add api details first.', $result['error']['details']['results'][0]['detail']);
        $this->assertSame([], $service->legacyLog);
        $this->assertSame('failed', $audit->rows[0]['payload']['status']);
    }

    public function test_a_sender_that_throws_is_a_failure_that_leaks_nothing(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $service->reply = static fn () => throw new \RuntimeException('failed for https://sms.example.test/send?user=SECRETUSER');
        $args = ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('SECRET', json_encode([$result, $audit->rows]));
    }

    public function test_after_two_unreachable_gateway_attempts_the_rest_are_not_attempted_and_say_so(): void
    {
        $many = [];
        $mobiles = [];
        foreach (range(1, 6) as $i) {
            $many[] = $this->account($i, 'STUDENT '.$i, 5000 + $i);
            $mobiles[$i] = '98765432'.sprintf('%02d', $i);
        }

        [$service] = $this->service($many, [], $mobiles);
        // curl error 28 is how the sender reports a timeout.
        $service->reply = static fn () => ['error' => 1, 'message' => 'Operation timed out-28'];
        $args = ['deadline' => '2026-10-31', 'top' => 6, 'channel' => 'sms'];

        $result = $service->send($this->scope(), $args, $service->preview($this->scope(), $args)['data']['fingerprint']);

        // Two attempts, then it stops: six messages must not mean six timeouts.
        $this->assertCount(2, $service->handed);
        $details = array_column($result['error']['details']['results'], 'detail');
        $this->assertStringContainsString('Not attempted', $details[5]);
        $this->assertSame(6, $result['error']['details']['failed']);
    }

    // ---------------------------------------------------- channel and wording

    public function test_the_approval_covers_the_channel_and_the_wording_so_changing_either_needs_a_new_one(): void
    {
        Http::fake(['*' => Http::response('OK id=1', 200)]);

        [$service] = $this->service($this->ledger(), $this->emails(), $this->mobiles());
        $email = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'email'];
        $approvedEmail = $service->preview($this->scope(), $email)['data']['fingerprint'];

        // The same list, but by SMS: the email approval does not carry over.
        $sms = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'sms'];
        $this->assertSame('recipients_changed', $service->send($this->scope(), $sms, $approvedEmail)['error']['code']);

        // The same list, same channel, new wording: neither does that.
        $reworded = $email + ['message' => 'Please pay {amount} by {deadline}.'];
        $this->assertSame('recipients_changed', $service->send($this->scope(), $reworded, $approvedEmail)['error']['code']);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_a_custom_message_is_what_is_previewed_and_what_is_sent_with_each_students_own_details(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());
        $args = ['deadline' => '2026-10-31', 'top' => 4, 'message' => 'Hello {name}, kindly clear {amount} by {deadline}.'];

        $preview = $service->preview($this->scope(), $args)['data'];

        $this->assertTrue($preview['message_is_custom']);
        $this->assertSame('Hello ASHA RAO, kindly clear ₹3,35,480 by 31 October 2026.', $preview['sample_message']);
        $this->assertSame([], $preview['warnings']);

        $service->send($this->scope(), $args, $preview['fingerprint']);

        Mail::assertSent(FeeReminderNotice::class, fn ($mail) => $mail->hasTo('divya@example.com')
            && str_contains($mail->messageHtml, 'Hello DIVYA IYER, kindly clear ₹4,500 by 31 October 2026.'));
    }

    public function test_a_custom_message_that_leaves_out_the_amount_or_the_deadline_is_warned_about(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());

        $warnings = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'message' => 'Please pay your fees soon.'])['data']['warnings'];

        $this->assertStringContainsString('deadline', implode(' ', $warnings));
        $this->assertStringContainsString('amount', implode(' ', $warnings));
    }

    public function test_markup_in_a_custom_message_is_stripped_and_an_over_long_one_is_refused(): void
    {
        [$service] = $this->service($this->ledger(), $this->emails());

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'message' => '<script>alert(1)</script>Pay {amount} by {deadline}'])['data'];
        $this->assertStringNotContainsString('<', $data['sample_message']);

        $long = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'message' => str_repeat('a', 500)]);
        $this->assertSame('message_too_long', $long['error']['code']);
    }

    public function test_a_long_sms_is_reported_as_several_parts(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());

        $data = $service->preview($this->scope(), [
            'deadline' => '2026-10-31', 'channel' => 'sms',
            'message' => str_repeat('Please clear your dues. ', 12).'{amount} by {deadline}',
        ])['data'];

        $this->assertGreaterThan(1, $data['sms']['segments']);
        $this->assertStringContainsString('parts long', implode(' ', $data['warnings']));
    }

    // ------------------------------------------------------------ permission

    public function test_only_a_school_administrator_may_remind_by_the_erps_own_definition(): void
    {
        [$service] = $this->service([], []);

        $admin = fn (bool $flag, ?int $profile) => new McpRequestContext(1, $flag ? 'admin' : 'staff', 61, [61], $profile, null, 2026, null, $flag, false);

        $check = new class extends AdministratorCheck
        {
            protected function chainsToProfileOne(int $profileId): bool
            {
                // Profile 5 "School admin" and 7 "Admin" chain to profile 1; 9 "Teacher" and 11 "Clerk" do not.
                return in_array($profileId, [1, 5, 7], true);
            }
        };

        $position = new class extends FeesPositionService
        {
            public function __construct()
            {
            }
        };
        $audit = new class extends AiAuditLogger
        {
            public function __construct()
            {
            }
        };
        $service = new FeesReminderService($position, $audit, $check);

        // The token's flag is empty for everyone in this estate, so the flag alone cannot decide.
        $this->assertTrue($service->mayRemind($admin(false, 7)), 'an Admin profile with an empty is_admin flag');
        $this->assertTrue($service->mayRemind($admin(false, 5)), 'a School admin profile');
        $this->assertTrue($service->mayRemind($admin(false, 1)), 'profile 1 itself');
        $this->assertTrue($service->mayRemind($admin(true, null)), 'a flagged administrator');
        $this->assertFalse($service->mayRemind($admin(false, 9)), 'a Teacher');
        $this->assertFalse($service->mayRemind($admin(false, 11)), 'a Clerk');
        $this->assertFalse($service->mayRemind($admin(false, null)), 'no profile at all');

        $student = new McpRequestContext(1, 'student', 61, [61], 7, null, 2026, null, false, true);
        $this->assertFalse($service->mayRemind($student), 'a student, whatever the profile says');
    }

    // ----------------------------------------------------------------- history

    public function test_the_ledger_is_read_back_as_sent_failed_or_skipped_never_as_delivered(): void
    {
        [$service] = $this->service([], []);

        $read = new class($service)
        {
            public function __construct(private readonly FeesReminderService $service)
            {
            }

            public function row(object $row): array
            {
                return (fn () => $this->reminderFromRow($row))->call($this->service);
            }
        };

        $ledgerRow = fn (string $status, string $outcome, string $channel = 'email') => (object) [
            'id' => 7, 'subject_id' => 12, 'outcome' => $outcome, 'created_at' => '2026-10-13 10:00:00', 'actor_id' => 3,
            'payload' => json_encode(['status' => $status, 'reference' => 'FR-1', 'subject_label' => 'ASHA RAO',
                'result' => ['channel' => $channel, 'outstanding' => 335480, 'deadline' => '2026-10-31', 'detail' => 'Accepted.', 'sent_to' => 'a***@example.com']]),
        ];

        $this->assertSame('sent', $read->row($ledgerRow('completed', 'success'))['status']);
        $this->assertSame('failed', $read->row($ledgerRow('failed', 'failure'))['status']);
        $this->assertSame('skipped', $read->row($ledgerRow('skipped', 'rejected'))['status']);

        $one = $read->row($ledgerRow('completed', 'success', 'sms'));
        $this->assertSame('ASHA RAO', $one['student_name']);
        $this->assertSame('sms', $one['channel']);
        $this->assertSame('2026-10-31', $one['deadline']);
        $this->assertSame('FR-1', $one['batch']);
        $this->assertSame(12, $one['student_id']);
    }

    public function test_every_saved_reminder_carries_an_honest_delivery_state_and_the_gateways_message_id(): void
    {
        [$service] = $this->service([], []);

        $read = fn (object $row) => (fn () => $this->reminderFromRow($row))->call($service);
        $row = fn (string $status, string $outcome, array $result = []) => (object) [
            'id' => 7, 'subject_id' => 12, 'outcome' => $outcome, 'created_at' => '2026-10-13 10:00:00', 'actor_id' => 3,
            'payload' => json_encode(['status' => $status, 'reference' => 'FR-1', 'subject_label' => 'ASHA RAO',
                'result' => $result + ['channel' => 'sms', 'outstanding' => 100, 'deadline' => '2026-10-31', 'detail' => 'x']]),
        ];

        // Accepted: delivery is UNKNOWN. Nothing the gateway or a mail server returns says more.
        $accepted = $read($row('completed', 'success', ['provider_id' => '3648178016739718']));
        $this->assertSame('unknown', $accepted['delivery_status']);
        $this->assertStringContainsString('delivery report is not available', $accepted['delivery_note']);
        $this->assertSame('3648178016739718', $accepted['provider_id']);

        $this->assertStringContainsString('inbox', $read($row('completed', 'success', ['channel' => 'email']))['delivery_note']);
        $this->assertSame('not_delivered', $read($row('failed', 'failure'))['delivery_status']);
        $this->assertSame('not_sent', $read($row('skipped', 'rejected'))['delivery_status']);

        // Never "delivered", for any stored status.
        foreach ([['completed', 'success'], ['failed', 'failure'], ['skipped', 'rejected']] as [$status, $outcome]) {
            $this->assertNotSame('delivered', $read($row($status, $outcome))['delivery_status']);
        }
    }

    public function test_an_sms_preview_shows_the_institutes_saved_template_and_the_finished_text(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles(), ['last_var' => '.(Generated at CMA)'] + self::GATEWAY);

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'])['data'];

        $this->assertSame('signature', $data['template']['kind']);
        $this->assertSame('.(Generated at CMA)', $data['template']['text']);
        // What the parent's phone shows: the wording, then the signature the Send SMS Parents sender adds.
        $this->assertStringEndsWith('by 31 October 2026..(Generated at CMA)', $data['final_message']);
        $this->assertSame($data['sample_message'].'.(Generated at CMA)', $data['final_message']);
        // The length is counted on what is actually sent.
        $this->assertSame(mb_strlen($data['final_message']), $data['sms']['characters']);
    }

    public function test_a_template_parameter_is_shown_and_not_added_to_the_text(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles(), ['last_var' => '&templateid=1707166555566064422'] + self::GATEWAY);

        $data = $service->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'])['data'];

        $this->assertSame('gateway_parameter', $data['template']['kind']);
        $this->assertSame('&templateid=1707166555566064422', $data['template']['name']);
        $this->assertSame($data['sample_message'], $data['final_message']);
    }

    public function test_changing_the_saved_template_after_approval_needs_a_new_approval(): void
    {
        [$before] = $this->service($this->ledger(), [], $this->mobiles(), ['last_var' => '.Old'] + self::GATEWAY);
        $fingerprint = $before->preview($this->scope(), ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'])['data']['fingerprint'];

        [$after] = $this->service($this->ledger(), [], $this->mobiles(), ['last_var' => '.New'] + self::GATEWAY);
        $result = $after->send($this->scope(), ['deadline' => '2026-10-31', 'top' => 1, 'channel' => 'sms'], $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame('recipients_changed', $result['error']['code']);
        $this->assertSame([], $after->handed);
    }

    // ------------------------------------------------------------------ WhatsApp

    private const WA_ARGS = ['deadline' => '2026-10-31', 'top' => 4, 'channel' => 'whatsapp'];

    public function test_a_whatsapp_preview_names_the_template_the_finished_text_and_masks_the_number(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());

        $result = $service->preview($this->scope(), self::WA_ARGS);
        $data = $result['data'];

        $this->assertTrue($result['success']);
        $this->assertSame('whatsapp', $data['channel']);
        $this->assertSame('WhatsApp', $data['channel_label']);
        $this->assertSame(['ASHA RAO', 'DIVYA IYER'], array_column($data['recipients'], 'name'));
        $this->assertSame('******3210', $data['recipients'][0]['contact_masked']);
        $this->assertSame('cloud_template', $data['template']['kind']);
        $this->assertSame('parents_', $data['template']['name']);
        $this->assertSame(
            'Dear ASHA RAO, your outstanding fee amount is Rs.3,35,480. Please submit your pending fees by 31 October 2026.',
            $data['final_message']
        );
        $this->assertSame('graph.facebook.com', $data['whatsapp']['host']);
        $this->assertStringContainsString('WhatsApp', implode(' ', $data['warnings']));
        // Neither the token nor the phone-number id leaves the server.
        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode($result));
        $this->assertStringNotContainsString('"111"', json_encode($result));
    }

    public function test_whatsapp_is_refused_up_front_when_the_institute_has_no_credentials(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());
        $service->whatsapp = null;

        $result = $service->preview($this->scope(), self::WA_ARGS);

        $this->assertFalse($result['success']);
        $this->assertSame('whatsapp_not_configured', $result['error']['code']);
        $this->assertStringContainsString('access token and phone number id', $result['message']);
        $this->assertSame([], $service->whatsappCalls);
    }

    public function test_a_whatsapp_message_is_sent_as_one_line_to_the_91_number_and_recorded_with_its_message_id(): void
    {
        [$service, $audit] = $this->service($this->ledger(), [], $this->mobiles());
        $fingerprint = $service->preview($this->scope(), self::WA_ARGS)['data']['fingerprint'];

        $result = $service->send($this->scope(), self::WA_ARGS, $fingerprint);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['sent']);
        $this->assertSame('WhatsApp', $result['data']['channel_label']);
        $this->assertStringContainsString('accepted by WhatsApp', $result['message']);
        $this->assertSame('919876543210', $service->whatsappCalls[0]['to']);
        $this->assertStringNotContainsString("\n", $service->whatsappCalls[0]['text']);
        $this->assertSame('accepted', $service->whatsappLog[0]['status']);
        $this->assertNull($service->whatsappLog[0]['error']);
        $this->assertSame('wamid.HBgM123', $service->whatsappLog[0]['providerId']);

        $ledger = array_values(array_filter($audit->rows, fn ($row) => ($row['payload']['result']['channel'] ?? null) === 'whatsapp'));
        $this->assertSame('wamid.HBgM123', $ledger[0]['payload']['result']['provider_id']);
        $this->assertSame('completed', $ledger[0]['payload']['status']);
        // The legacy SMS log is for SMS only.
        $this->assertSame([], $service->legacyLog);
    }

    /** @return array<string, array{0:array<string, mixed>}> */
    public static function whatsappRefusals(): array
    {
        return [
            'api error' => [['error' => ['message' => 'Template not approved', 'code' => 132001]]],
            'per-message error' => [['messages' => [['error' => ['message' => 'blocked']]]]],
            'no id and no error' => [['messaging_product' => 'whatsapp']],
            'empty reply' => [[]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('whatsappRefusals')]
    public function test_a_whatsapp_refusal_is_never_reported_as_sent(array $reply): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());
        $service->whatsappReply = fn () => $reply;
        $fingerprint = $service->preview($this->scope(), self::WA_ARGS)['data']['fingerprint'];

        $result = $service->send($this->scope(), self::WA_ARGS, $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame('send_failed', $result['error']['code']);
        $this->assertSame(0, $result['error']['details']['sent']);
        $this->assertSame(2, $result['error']['details']['failed']);
    }

    public function test_changing_the_channel_after_approval_needs_a_new_approval(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());
        $fingerprint = $service->preview($this->scope(), ['channel' => 'sms'] + self::WA_ARGS)['data']['fingerprint'];

        $result = $service->send($this->scope(), self::WA_ARGS, $fingerprint);

        $this->assertFalse($result['success']);
        $this->assertSame('recipients_changed', $result['error']['code']);
        $this->assertSame([], $service->whatsappCalls);
    }

    public function test_a_student_with_no_usable_mobile_is_skipped_on_whatsapp_too(): void
    {
        [$service] = $this->service($this->ledger(), [], $this->mobiles());

        $data = $service->preview($this->scope(), self::WA_ARGS)['data'];

        $this->assertSame('No mobile number on the student record.', $data['unreachable'][0]['reason']);
        $this->assertSame('The mobile number on record is not a valid 10-digit Indian mobile.', $data['unreachable'][1]['reason']);
    }

    /** @return array<string, array{0:?string, 1:string}> */
    public static function whatsappReceipts(): array
    {
        return [
            'read' => ['read', 'read'],
            'delivered' => ['delivered', 'delivered'],
            'failed later' => ['failed (131026)', 'failed'],
            'only sent' => ['sent', 'pending'],
            'no receipt yet' => [null, 'pending'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('whatsappReceipts')]
    public function test_the_status_shows_what_whatsapp_has_reported_since(?string $reported, string $expected): void
    {
        [$service] = $this->service([], []);
        $service->whatsappReported = $reported;

        $row = (object) [
            'id' => 7, 'subject_id' => 12, 'outcome' => 'success', 'created_at' => '2026-10-15 10:00:00', 'actor_id' => 3,
            'payload' => json_encode(['status' => 'completed', 'reference' => 'FR-1', 'subject_label' => 'ASHA RAO',
                'result' => ['channel' => 'whatsapp', 'outstanding' => 100, 'deadline' => '2026-10-31', 'detail' => 'Accepted.', 'provider_id' => 'wamid.X']]),
        ];

        $this->assertSame($expected, (fn () => $this->reminderFromRow($row))->call($service)['delivery_status']);
    }

    public function test_delivery_is_counted_from_each_records_own_state_never_from_the_channel(): void
    {
        [$service] = $this->service([], []);

        $counts = (fn (array $rows) => $this->deliveryCounts($rows))->bindTo($service, FeesReminderService::class);
        $sentence = (fn (array $rows) => $this->deliverySentence($rows))->bindTo($service, FeesReminderService::class);

        $rows = [
            ['delivery_status' => 'read'], ['delivery_status' => 'delivered'], ['delivery_status' => 'pending'],
            ['delivery_status' => 'unknown'], ['delivery_status' => 'unknown'], ['delivery_status' => 'not_delivered'], ['delivery_status' => 'not_sent'],
        ];

        $c = $counts($rows);
        $this->assertSame(2, $c['confirmed_delivered']);
        $this->assertSame(1, $c['pending']);
        $this->assertSame(2, $c['unknown']);
        $this->assertSame(1, $c['not_delivered']);
        $this->assertSame(1, $c['not_sent']);

        // SMS and email can only ever be "unknown"; the sentence says so and does not call them delivered.
        $text = $sentence([['delivery_status' => 'unknown']]);
        $this->assertStringContainsString('Delivery of 1 is unknown', $text);
        $this->assertStringNotContainsString('confirmed', $text);
        $this->assertSame(' 1 not delivered.', $sentence([['delivery_status' => 'not_delivered']]));
        $this->assertStringContainsString('never reported delivered', $sentence([['delivery_status' => 'not_confirmed']]));
        $this->assertSame('', $sentence([]));
    }

    public function test_the_gateways_message_id_is_read_from_its_reply_when_it_has_one(): void
    {
        [$service] = $this->service([], []);

        $id = fn (string $body) => (fn () => $this->providerMessageId($body))->call($service);

        $this->assertSame('3648178016739718', $id('{"status":"OK","data":[{"id":"178016739-1","mobile":"916353662940","status":"SUBMITTED"}],"msgid":"3648178016739718"}'));
        $this->assertSame('178016739-1', $id('{"status":"OK","data":[{"id":"178016739-1"}]}'));
        $this->assertNull($id('Message accepted'));
        $this->assertNull($id('{"status":"OK"}'));
    }

    // ------------------------------------------------------------ formatting

    public function test_amounts_use_indian_grouping_and_paise_only_when_there_are_some(): void
    {
        [$service] = $this->service([], []);

        $this->assertSame('₹0', $service->rupees(0));
        $this->assertSame('₹999', $service->rupees(999));
        $this->assertSame('₹1,000', $service->rupees(1000));
        $this->assertSame('₹1,00,000', $service->rupees(100000));
        $this->assertSame('₹12,34,567.50', $service->rupees(1234567.5));
        $this->assertSame('₹1,23,45,678', $service->rupees(12345678));
    }
}
