<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Flows\FeesRemindersFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Domain\AI\Support\AiAuditLogger;
use App\Mail\FeeReminderNotice;
use App\Services\Mcp\FeesPositionService;
use App\Services\Mcp\FeesReminderService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ToolResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The chat flow for fee reminders, end to end, with no database and no real email.
 *
 * The fake tool caller behaves like the real tool: a preview returns the list and a token and
 * changes nothing; spending the token sends. Everything in between — what the person is asked,
 * what they are shown, what "yes" approves — is the real flow and the real service.
 */
class FeesRemindersFlowTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $ledger;

    /** @var array<int, string|null> */
    private array $emails;

    /** @var array<int, string|null> */
    private array $mobiles;

    /** @var array<int, array<string, mixed>> */
    private array $students;

    /** @var array<int, array<string, mixed>> */
    public array $historyRows = [];

    /** @var object */
    private $caller;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        Mail::fake();

        $this->ledger = [
            ['student_id' => 1, 'student_name' => 'ASHA RAO', 'standard_name' => 'XI', 'outstanding' => 335480],
            ['student_id' => 2, 'student_name' => 'BINA SHAH', 'standard_name' => 'IX', 'outstanding' => 271440],
            ['student_id' => 3, 'student_name' => 'CHIRAG DESAI', 'standard_name' => 'VIII', 'outstanding' => 120000],
            ['student_id' => 4, 'student_name' => 'DIVYA IYER', 'standard_name' => 'X', 'outstanding' => 4500],
        ];
        $this->emails = [1 => 'asha@example.com', 2 => null, 3 => 'chirag@example.com', 4 => 'divya@example.com'];
        $this->mobiles = [1 => '9876543210', 2 => '9876543211', 3 => null, 4 => '9876543213'];
        $this->students = [
            ['id' => 1, 'student_name' => 'ASHA RAO', 'enrollment_no' => '1001'],
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function flow(?bool $admin = true): FeesRemindersFlow
    {
        $test = $this;

        $position = new class($this) extends FeesPositionService
        {
            public function __construct(private readonly FeesRemindersFlowTest $test)
            {
            }

            public function accounts(McpRequestContext $context, array $arguments): array
            {
                $rows = $this->test->ledgerRows();

                if (! empty($arguments['student_ids'])) {
                    $rows = array_values(array_filter($rows, fn ($r) => in_array($r['student_id'], $arguments['student_ids'], true)));
                }

                return ToolResult::success('fees.outstanding_accounts', 'ok', ['accounts' => $rows]);
            }
        };

        $audit = new class extends AiAuditLogger
        {
            public function __construct()
            {
            }

            public function record(string $eventType, ?McpRequestContext $context = null, array $options = []): ?int
            {
                return 1;
            }
        };

        $service = new class($position, $audit, $this) extends FeesReminderService
        {
            public function __construct($position, $audit, private readonly FeesRemindersFlowTest $test)
            {
                parent::__construct($position, $audit);
            }

            protected function contactsFor(McpRequestContext $context, array $studentIds): array
            {
                $contacts = [];

                foreach ($studentIds as $id) {
                    $contacts[$id] = ['email' => $this->test->emailMap()[$id] ?? null, 'mobile' => $this->test->mobileMap()[$id] ?? null];
                }

                return $contacts;
            }

            protected function smsGateway(McpRequestContext $context): ?array
            {
                return ['url' => 'https://sms.example.test/send?', 'pram' => 'k=1', 'mobile_var' => '&to=', 'text_var' => '&m=', 'last_var' => ''];
            }

            protected function dispatchSms(array $gateway, string $mobile, string $text): array
            {
                return ['status' => 'sent', 'detail' => 'Accepted by the SMS gateway (HTTP 200): OK. Delivery to the phone is not reported back.', 'outage' => false];
            }

            protected function logLegacySms(int $studentId, string $text, string $mobile, McpRequestContext $context): void
            {
            }

            protected function whatsappConfiguration(McpRequestContext $context): ?array
            {
                return ['cloud_api_access_token' => 'SECRET-TOKEN', 'cloud_api_phone_number_id' => '111'];
            }

            protected function sendWhatsappRequest(string $to, string $text, string $accessToken, string $phoneNumberId): array
            {
                return ['messages' => [['id' => 'wamid.FLOW1']]];
            }

            protected function logWhatsapp(McpRequestContext $context, array $recipient, string $text, string $status, ?string $error, ?string $providerId): void
            {
            }

            public function history(McpRequestContext $context, array $arguments): array
            {
                $rows = $this->test->historyRows;

                if (! empty($arguments['batch'])) {
                    $rows = array_values(array_filter($rows, fn ($r) => strcasecmp($r['batch'], $arguments['batch']) === 0));
                }

                $sent = count(array_filter($rows, fn ($r) => $r['status'] === 'sent'));
                $failed = count(array_filter($rows, fn ($r) => $r['status'] === 'failed'));

                return ToolResult::success('fees.reminder_history', count($rows).' reminder records.', [
                    'count' => count($rows), 'accepted' => $sent, 'failed' => $failed, 'skipped' => 0,
                    'delivery' => ['confirmed_delivered' => 0, 'unknown' => $sent, 'not_delivered' => $failed, 'not_sent' => 0],
                    'reminders' => $rows,
                ]);
            }
        };

        $this->caller = new class($service, $test, $admin) extends McpToolCaller
        {
            /** @var array<int, array<string, mixed>> */
            public array $log = [];

            /** When true, the history tool is not available to this turn (not bound, or refused). */
            public bool $unavailable = false;

            public function __construct(
                private readonly FeesReminderService $service,
                private readonly FeesRemindersFlowTest $test,
                private readonly ?bool $admin,
            ) {
            }

            public function confirmable(StageContext $context, string $tool, array $arguments, string $why): array
            {
                $this->log[] = ['confirmable', $tool, $arguments];

                if ($this->admin !== true) {
                    return ['token' => null, 'preview' => [], 'error' => 'Only an administrator may send fee reminders.'];
                }

                return ['token' => 'tok-'.count($this->log), 'preview' => $this->service->preview($context->scope, $arguments), 'error' => null];
            }

            public function call(StageContext $context, string $tool, array $arguments, string $why, ?string $confirmationToken = null): ?array
            {
                $this->log[] = ['call', $tool, $arguments, $confirmationToken];

                if ($this->unavailable && $tool === 'fees.reminder_history') {
                    return null;
                }

                return match ($tool) {
                    'fees.reminder_history' => $this->service->history($context->scope, $arguments),
                    'fees.outstanding_accounts' => ToolResult::success('fees.outstanding_accounts', 'ok', ['accounts' => array_slice(array_values(array_filter(
                        $this->test->ledgerRows(),
                        fn ($r) => empty($arguments['student_ids']) || in_array($r['student_id'], $arguments['student_ids'], true)
                    )), 0, (int) ($arguments['limit'] ?? 50))]),
                    'students.search' => ['students' => $this->test->studentMatches((string) ($arguments['query'] ?? ''))],
                    'fees.send_reminders' => $confirmationToken === null
                        ? null
                        : $this->service->send($context->scope, $arguments, $arguments['approved_fingerprint'] ?? null),
                    default => null,
                };
            }
        };

        return new FeesRemindersFlow($this->caller, $service);
    }

    // The fixtures, reachable from the anonymous classes above.
    public function ledgerRows(): array
    {
        return $this->ledger;
    }

    public function emailMap(): array
    {
        return $this->emails;
    }

    public function mobileMap(): array
    {
        return $this->mobiles;
    }

    /** @return array<int, array<string, mixed>> */
    public function studentMatches(string $query): array
    {
        return array_values(array_filter($this->students, fn ($s) => stripos($s['student_name'], $query) !== false));
    }

    /** @param  array<string, mixed>  $memory */
    private function context(string $question, ?array $pending = null, string $role = 'admin', array $memory = []): StageContext
    {
        $context = new StageContext(
            question: $question,
            scope: new McpRequestContext(1, $role, 61, [61], null, null, 2026, null, $role === 'admin', false),
            module: new ModuleCapability(
                key: 'fees',
                label: 'Fees',
                capabilities: ['conversational' => true],
                mcpTools: ['fees.send_reminders', 'fees.reminder_history', 'fees.outstanding_accounts', 'students.search'],
            ),
        );

        $context->thread = ['memory' => $memory];

        if ($pending !== null) {
            $context->thread['memory']['pending_action'] = $pending;
        }

        return $context;
    }

    private function sentTo(): array
    {
        $to = [];
        Mail::assertSent(FeeReminderNotice::class, function ($mail) use (&$to) {
            $to[] = $mail->to[0]['address'] ?? null;

            return true;
        });

        return $to;
    }

    // ------------------------------------------------------------------ steps

    public function test_asking_without_a_deadline_lists_who_would_be_reminded_and_asks_by_when(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Send fee reminders to the top 3 students who owe the most'));

        $this->assertSame('need_deadline', $result['state']);
        $this->assertSame(['ASHA RAO', 'BINA SHAH', 'CHIRAG DESAI'], array_column($result['data']['accounts'], 'student_name'));
        $this->assertSame('₹3,35,480', $result['data']['accounts'][0]['outstanding_label']);
        $this->assertSame('need_deadline', $result['pending']['state']);
        $this->assertSame(3, $result['pending']['args']['top']);
        Mail::assertNothingSent();
    }

    public function test_a_deadline_in_the_sentence_goes_straight_to_the_preview_and_sends_nothing(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        $this->assertSame('ready', $result['state']);
        $this->assertSame('2026-10-31', $result['data']['deadline']);
        $this->assertSame(3, $result['data']['recipient_count']);
        $this->assertSame(['BINA SHAH'], array_column($result['data']['unreachable'], 'name'));
        $this->assertSame('ready', $result['pending']['state']);
        $this->assertNotSame('', $result['pending']['fingerprint']);
        Mail::assertNothingSent();
    }

    public function test_replying_with_the_deadline_moves_from_asking_to_the_preview(): void
    {
        $flow = $this->flow();
        $asked = $flow->start($this->context('Notify the top 3 students who owe the most'));

        $result = $flow->advance($this->context('by 5 November', $asked['pending']), $asked['pending']);

        $this->assertSame('ready', $result['state']);
        $this->assertSame('2026-11-05', $result['data']['deadline']);
        $this->assertSame(3, $result['pending']['args']['top']);
        Mail::assertNothingSent();
    }

    public function test_a_reply_with_no_date_asks_again_instead_of_guessing_one(): void
    {
        $flow = $this->flow();
        $asked = $flow->start($this->context('Notify the top 3 students who owe the most'));

        $result = $flow->advance($this->context('as soon as possible', $asked['pending']), $asked['pending']);

        $this->assertSame('need_deadline', $result['state']);
        $this->assertStringContainsString('need a payment deadline', $result['message']);
        Mail::assertNothingSent();
    }

    public function test_a_deadline_that_has_passed_is_asked_for_again_with_the_reason(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Remind the top 3 defaulters to pay by 1 October'));

        $this->assertSame('need_deadline', $result['state']);
        // The reason is now specific: which date, and that it has passed, rather than "cannot be used".
        $this->assertStringContainsString('1 October 2026 has already passed', $result['message']);
        Mail::assertNothingSent();
    }

    public function test_yes_sends_exactly_the_list_that_was_shown_and_reports_each_outcome(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        $result = $flow->advance($this->context('Yes', $ready['pending']), $ready['pending']);

        $this->assertSame('sent', $result['state']);
        $this->assertNull($result['pending']);
        $this->assertSame(3, $result['data']['sent']);
        $this->assertSame(1, $result['data']['skipped']);
        $this->assertEqualsCanonicalizing(['asha@example.com', 'chirag@example.com', 'divya@example.com'], $this->sentTo());

        // The tool was handed the fingerprint of the list the person was shown.
        $send = array_values(array_filter($this->caller->log, fn ($e) => $e[0] === 'call' && $e[1] === 'fees.send_reminders'))[0];
        $this->assertSame($ready['pending']['fingerprint'], $send[2]['approved_fingerprint']);
        $this->assertNotNull($send[3]);
    }

    public function test_if_the_ledger_moved_since_the_preview_yes_sends_nothing_and_shows_the_new_list(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        // Somebody pays between the preview and the "yes".
        $this->ledger[0]['outstanding'] = 100;

        $result = $flow->advance($this->context('Yes', $ready['pending']), $ready['pending']);

        $this->assertSame('ready', $result['state']);
        $this->assertStringContainsString('list changed', $result['message']);
        $this->assertNotSame($ready['pending']['fingerprint'], $result['pending']['fingerprint']);
        Mail::assertNothingSent();
    }

    public function test_changing_the_count_or_deadline_shows_a_new_list_and_does_not_send(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        $result = $flow->advance($this->context('make it the top 2 by 5 November', $ready['pending']), $ready['pending']);

        $this->assertSame('ready', $result['state']);
        $this->assertSame('2026-11-05', $result['data']['deadline']);
        $this->assertSame(2, $result['pending']['args']['top']);
        Mail::assertNothingSent();
    }

    public function test_an_unclear_reply_never_counts_as_approval(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        foreach (['hmm', 'what do they owe again?', 'yes but only 2', 'No'] as $reply) {
            $result = $flow->advance($this->context($reply, $ready['pending']), $ready['pending']);

            $this->assertNotSame('sent', $result['state'], "'{$reply}' sent reminders");
        }

        Mail::assertNothingSent();
    }

    public function test_a_named_student_is_found_by_name_and_only_that_student_is_prepared(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Remind Asha Rao to pay by 25 October'));

        $this->assertSame('ready', $result['state']);
        $this->assertSame([1], $result['pending']['args']['student_ids']);
        $this->assertSame(['ASHA RAO'], array_column($result['data']['recipients'], 'name'));
        $this->assertSame('The student you chose', $result['data']['selection']);
        Mail::assertNothingSent();
    }

    public function test_a_student_number_in_the_sentence_selects_exactly_that_student(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Send a fee reminder to student 3 by 25 October'));

        $this->assertSame('ready', $result['state']);
        $this->assertSame([3], $result['pending']['args']['student_ids']);
        $this->assertSame(['CHIRAG DESAI'], array_column($result['data']['recipients'], 'name'));
        $this->assertSame('The student you chose', $result['data']['selection']);
        Mail::assertNothingSent();
    }

    public function test_a_student_who_owes_nothing_is_reported_not_reminded(): void
    {
        $flow = $this->flow();

        // Student 99 is not in the ledger's list of those who owe.
        $result = $flow->start($this->context('Send a fee reminder to student 99 by 25 October'));

        $this->assertNotSame('ready', $result['state']);
        $this->assertNull($result['pending']);
        Mail::assertNothingSent();
    }

    public function test_a_name_that_matches_nobody_or_several_people_prepares_nothing(): void
    {
        $flow = $this->flow();

        $missing = $flow->start($this->context('Remind Zed Nobody to pay by 25 October'));
        $this->assertSame('not_found', $missing['state']);
        $this->assertNull($missing['pending']);

        $this->students[] = ['id' => 9, 'student_name' => 'ASHA RAO', 'enrollment_no' => '2002'];
        $both = $flow->start($this->context('Remind Asha Rao to pay by 25 October'));
        $this->assertSame('ambiguous', $both['state']);
        $this->assertCount(2, $both['data']['students']);
        $this->assertNull($both['pending']);
        Mail::assertNothingSent();
    }

    public function test_a_person_who_may_not_send_is_told_so_and_nothing_is_prepared_or_sent(): void
    {
        $flow = $this->flow(admin: false);

        $result = $flow->start($this->context('Remind the top 3 defaulters to pay by 31 October', null, 'staff'));

        $this->assertSame('blocked', $result['state']);
        $this->assertStringContainsString('limited to school administrators', $result['message']);
        $this->assertNull($result['pending']);
        Mail::assertNothingSent();

        // Said at once: no list is read and no tool is asked for a preview that would only be refused.
        $this->assertSame([], $this->caller->log);

        // The same holds mid-way through, if the thread belongs to somebody who is not allowed.
        $pending = ['kind' => FeesRemindersFlow::KIND, 'state' => 'ready', 'args' => ['deadline' => '2026-10-31'], 'fingerprint' => 'x'];
        $later = $flow->advance($this->context('Yes', $pending, 'staff'), $pending);
        $this->assertSame('blocked', $later['state']);
        $this->assertSame([], $this->caller->log);
    }

    public function test_when_nobody_can_be_emailed_it_says_what_is_missing_instead_of_pretending(): void
    {
        $this->emails = [1 => null, 2 => null, 3 => null, 4 => null];
        $flow = $this->flow();

        $result = $flow->start($this->context('Remind the top 3 defaulters to pay by 31 October'));

        $this->assertSame('nothing_to_send', $result['state']);
        $this->assertCount(3, $result['data']['unreachable']);
        Mail::assertNothingSent();

        // The task stays open so the other channel can be tried, but there is no reviewed list to
        // approve, so a "yes" cannot send anything.
        $this->assertSame('', $result['pending']['fingerprint']);
        $yes = $flow->advance($this->context('Yes', $result['pending']), $result['pending']);
        $this->assertSame('blocked', $yes['state']);
        Mail::assertNothingSent();

        // ... and SMS is reachable for those same students (BINA SHAH has a mobile).
        $sms = $flow->advance($this->context('send it by SMS instead', $result['pending']), $result['pending']);
        $this->assertSame('ready', $sms['state']);
        $this->assertSame('sms', $sms['data']['channel']);
        Mail::assertNothingSent();
    }

    public function test_status_reads_the_record_and_only_for_staff(): void
    {
        $flow = $this->flow();

        // Not allowed for anyone but staff or an administrator.
        $this->assertSame('blocked', $flow->status($this->context('Were the reminders sent?', null, 'student'))['state']);
    }

    // ------------------------------------------------------------------ SMS

    public function test_asking_for_an_sms_reminder_previews_the_text_and_the_numbers_and_sends_nothing(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Send an SMS fee reminder to the top 4 students by 31 October'));

        $this->assertSame('ready', $result['state']);
        $this->assertSame('sms', $result['data']['channel']);
        $this->assertSame('sms', $result['pending']['args']['channel']);
        $this->assertSame(['ASHA RAO', 'BINA SHAH', 'DIVYA IYER'], array_column($result['data']['recipients'], 'name'));
        $this->assertSame(['CHIRAG DESAI'], array_column($result['data']['unreachable'], 'name'));
        $this->assertSame('******3210', $result['data']['recipients'][0]['contact_masked']);
        $this->assertStringStartsWith('Dear ASHA RAO, your outstanding fee amount is Rs.3,35,480.', $result['data']['sample_message']);
        Mail::assertNothingSent();
    }

    public function test_yes_to_an_sms_preview_sends_by_sms_and_reports_the_gateways_outcome_per_student(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Send an SMS fee reminder to the top 4 students by 31 October'));

        $result = $flow->advance($this->context('Yes', $ready['pending']), $ready['pending']);

        $this->assertSame('sent', $result['state']);
        $this->assertSame('sms', $result['data']['channel']);
        $this->assertSame(3, $result['data']['sent']);
        $this->assertSame(1, $result['data']['skipped']);
        $this->assertStringContainsString('Accepted by the SMS gateway', $result['data']['results'][0]['detail']);
        $this->assertStringContainsString('Delivery to the phone is not reported back', $result['data']['results'][0]['detail']);
        // Nothing went by email.
        Mail::assertNothingSent();
    }

    public function test_the_channel_can_be_switched_after_the_preview_and_the_old_approval_does_not_carry_over(): void
    {
        $flow = $this->flow();
        $email = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));
        $this->assertSame('email', $email['data']['channel']);

        $sms = $flow->advance($this->context('send it by SMS instead', $email['pending']), $email['pending']);

        $this->assertSame('ready', $sms['state']);
        $this->assertSame('sms', $sms['data']['channel']);
        $this->assertNotSame($email['pending']['fingerprint'], $sms['pending']['fingerprint']);
        Mail::assertNothingSent();

        // "SMS" is a change, not an approval.
        $this->assertFalse($flow->looksLikeApproval('yes, by SMS'));
    }

    public function test_the_word_text_alone_is_not_a_request_for_sms(): void
    {
        $flow = $this->flow();

        $this->assertNull($flow->parseRequest('change the message text')['channel']);
        $this->assertSame('sms', $flow->parseRequest('text them')['channel']);
        $this->assertSame('sms', $flow->parseRequest('send an SMS')['channel']);
        $this->assertSame('sms', $flow->parseRequest('send a text message')['channel']);
        $this->assertSame('email', $flow->parseRequest('email them instead')['channel']);
        $this->assertNull($flow->parseRequest('remind the top 5')['channel']);
    }

    // ------------------------------------------------------------- the wording

    public function test_the_message_can_be_edited_and_the_new_wording_is_previewed_not_sent(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        $edited = $flow->advance(
            $this->context('change the message to "Hello {name}, please clear {amount} by {deadline}."', $ready['pending']),
            $ready['pending']
        );

        $this->assertSame('ready', $edited['state']);
        $this->assertTrue($edited['data']['message_is_custom']);
        $this->assertSame('Hello ASHA RAO, please clear ₹3,35,480 by 31 October 2026.', $edited['data']['sample_message']);
        $this->assertSame('Hello {name}, please clear {amount} by {deadline}.', $edited['pending']['args']['message']);
        $this->assertNotSame($ready['pending']['fingerprint'], $edited['pending']['fingerprint']);
        Mail::assertNothingSent();

        // And what is sent is exactly that.
        $sent = $flow->advance($this->context('Yes', $edited['pending']), $edited['pending']);
        $this->assertSame('sent', $sent['state']);
        Mail::assertSent(FeeReminderNotice::class, fn ($mail) => $mail->hasTo('divya@example.com')
            && str_contains($mail->messageHtml, 'Hello DIVYA IYER, please clear ₹4,500 by 31 October 2026.'));
    }

    public function test_asking_to_edit_the_message_without_giving_the_wording_explains_how_and_keeps_the_task(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 4 defaulters to pay by 31 October'));

        $result = $flow->advance($this->context('Edit the reminder message.', $ready['pending']), $ready['pending']);

        $this->assertSame('ready', $result['state']);
        $this->assertStringContainsString('Tell me the wording', $result['message']);
        $this->assertStringContainsString('{amount}', $result['message']);
        $this->assertFalse($result['data']['message_is_custom']);
        Mail::assertNothingSent();
    }

    // ------------------------------------------------- what is on the screen

    /** @return array<string, mixed> */
    private function shownList(): array
    {
        $rows = array_map(fn ($r) => [
            'student_id' => $r['student_id'], 'student_name' => $r['student_name'],
            'standard_name' => $r['standard_name'], 'outstanding' => $r['outstanding'],
        ], $this->ledger);

        $set = \App\Domain\AI\Conversation\ResultSet::fromRows('fees', 'fees.outstanding_accounts', 'students', $rows, 'Which students owe the most?');

        return ['last_result_set' => $set->toArray()];
    }

    public function test_all_of_them_means_the_students_that_were_just_listed(): void
    {
        $flow = $this->flow();

        $result = $flow->start($this->context('Send reminders to all 4 students by 31 October', null, 'admin', $this->shownList()));

        $this->assertSame('ready', $result['state']);
        $this->assertSame([1, 2, 3, 4], $result['pending']['args']['student_ids']);
        $this->assertSame('The students you chose', $result['data']['selection']);
        Mail::assertNothingSent();
    }

    public function test_the_second_student_and_a_printed_name_and_the_first_two_are_read_against_the_list(): void
    {
        $flow = $this->flow();
        $memory = $this->shownList();

        $second = $flow->start($this->context('Remind the fourth student to pay by 31 October', null, 'admin', $memory));
        $this->assertSame([4], $second['pending']['args']['student_ids']);

        $named = $flow->start($this->context('send a reminder to ASHA RAO by 31 October', null, 'admin', $memory));
        $this->assertSame([1], $named['pending']['args']['student_ids']);

        $firstTwo = $flow->start($this->context('remind the first 2 by 31 October', null, 'admin', $memory));
        $this->assertSame([1, 2], $firstTwo['pending']['args']['student_ids']);
        Mail::assertNothingSent();
    }

    public function test_this_student_means_the_one_the_conversation_is_about(): void
    {
        $flow = $this->flow();
        $memory = ['selected_record' => ['item' => ['position' => 1, 'id' => 4, 'title' => 'DIVYA IYER', 'row' => ['student_id' => 4]], 'singular' => 'student', 'module' => 'fees']];

        $result = $flow->start($this->context('Send an SMS fee reminder to this student by 31 October', null, 'admin', $memory));

        $this->assertSame('ready', $result['state']);
        $this->assertSame([4], $result['pending']['args']['student_ids']);
        $this->assertSame('sms', $result['data']['channel']);
    }

    public function test_this_student_while_a_class_is_selected_asks_which_student_of_that_class(): void
    {
        $flow = $this->flow();
        $class = ['standard_id' => 6, 'standard_name' => 'Class 6'];
        $memory = ['selected_record' => ['item' => ['position' => 1, 'id' => 6, 'title' => 'Class 6', 'row' => $class], 'singular' => 'class', 'module' => 'fees']];

        $result = $flow->start($this->context('Send an SMS fee reminder to this student.', null, 'admin', $memory));

        // The class id 6 is not a student id: nobody is chosen, and the question is asked about that class.
        $this->assertSame('need_student', $result['state']);
        $this->assertSame('Students in this class who owe the most', $result['data']['selection']);
        $calls = array_values(array_filter($this->caller->log, fn ($e) => $e[0] === 'call' && $e[1] === 'fees.outstanding_accounts'));
        $this->assertSame(6, $calls[0][2]['standard_id']);
        $this->assertArrayNotHasKey('student_ids', $calls[0][2]);
        Mail::assertNothingSent();

        // Picking one of them drops the class and carries on with that student.
        $picked = $flow->advance($this->context('the first one', $result['pending'], 'admin', $this->shownList()), $result['pending']);
        $this->assertSame('need_deadline', $picked['state']);
        $this->assertSame([1], $picked['pending']['args']['student_ids']);
        $this->assertArrayNotHasKey('standard_id', $picked['pending']['args']);
    }

    public function test_a_reference_with_nothing_on_screen_asks_which_student_instead_of_guessing(): void
    {
        $flow = $this->flow();

        // "this student" with no selected student, and a bare "a student" / "student id".
        foreach (['Send a fee reminder to this student by 31 October', 'Send a fee reminder to student id', 'remind a student'] as $sentence) {
            $result = $flow->start($this->context($sentence));

            $this->assertSame('need_student', $result['state'], $sentence);
            $this->assertSame('need_student', $result['pending']['state']);
            $this->assertNotSame([], $result['data']['accounts']);
        }

        Mail::assertNothingSent();
    }

    public function test_answering_which_student_with_a_position_or_a_number_moves_on(): void
    {
        $flow = $this->flow();
        $asked = $flow->start($this->context('Send a fee reminder to student id'));
        $memory = $this->shownList();

        $byPosition = $flow->advance($this->context('the third one', $asked['pending'], 'admin', $memory), $asked['pending']);
        $this->assertSame('need_deadline', $byPosition['state']);
        $this->assertSame([3], $byPosition['pending']['args']['student_ids']);

        $byNumber = $flow->advance($this->context('student 4 by 5 November', $asked['pending'], 'admin', $memory), $asked['pending']);
        $this->assertSame('ready', $byNumber['state']);
        $this->assertSame([4], $byNumber['pending']['args']['student_ids']);

        $unclear = $flow->advance($this->context('hmm not sure', $asked['pending'], 'admin', $memory), $asked['pending']);
        $this->assertSame('need_student', $unclear['state']);
        Mail::assertNothingSent();
    }

    // -------------------------------------------------------------- wording

    public function test_cancel_and_approval_wording(): void
    {
        $flow = $this->flow();

        foreach (['cancel', 'Cancel.', 'never mind', "don't send", 'stop', 'No'] as $text) {
            $this->assertTrue($flow->looksLikeCancel($text), $text);
            $this->assertFalse($flow->looksLikeApproval($text), $text);
        }

        foreach (['Yes', 'yes, send them', 'Yes, send the reminders.', 'ok', 'go ahead', 'Send it'] as $text) {
            $this->assertTrue($flow->looksLikeApproval($text), $text);
        }

        foreach (['yes but make it 5', 'yes 31 October', 'change the deadline to 5 Nov', 'maybe'] as $text) {
            $this->assertFalse($flow->looksLikeApproval($text), $text);
        }
    }

    // ---------------------------------------------------- dates and status (2026-10-13)

    public function test_an_impossible_date_is_explained_not_treated_as_no_date(): void
    {
        $result = $this->flow()->start($this->context('Send fee reminders to the top 3 students by 31 sept'));

        $this->assertSame('need_deadline', $result['state']);
        $this->assertStringContainsString('September has only 30 days', $result['message']);
        $this->assertStringContainsString('has also passed', $result['message']);
        $this->assertStringNotContainsString('I need a payment deadline', $result['message']);
        Mail::assertNothingSent();
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function badDates(): array
    {
        return [
            'february in a non-leap year' => ['by 29 February 2027', 'not a leap year'],
            'february 30th' => ['by 30 Feb', 'never has more than 29 days'],
            'a month that does not exist' => ['by 12/13/2026', 'no month number 13'],
            'a date that has passed' => ['by 1 October', 'already passed'],
            'more than a year away' => ['by 5 November 2027', 'more than a year away'],
            'a day that does not exist' => ['by 40 October', 'October has only 31 days'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badDates')]
    public function test_each_kind_of_bad_date_gets_its_own_reason(string $text, string $reason): void
    {
        $result = $this->flow()->start($this->context('Send fee reminders to the top 3 students '.$text));

        $this->assertStringContainsString($reason, $result['message']);
    }

    public function test_leap_day_in_a_leap_year_and_ordinary_dates_are_accepted(): void
    {
        $this->assertNull(FeesRemindersFlow::deadlineProblem('by 29 February 2028', Carbon::parse('2027-10-13')));
        $this->assertNull(FeesRemindersFlow::deadlineProblem('by 31 October', Carbon::parse('2026-10-13')));
        $this->assertNull(FeesRemindersFlow::deadlineProblem('by 2026-12-31', Carbon::parse('2026-10-13')));
        $this->assertNull(FeesRemindersFlow::deadlineProblem('tomorrow', Carbon::parse('2026-10-13')));
        $this->assertNull(FeesRemindersFlow::deadlineProblem('the top 5 students', Carbon::parse('2026-10-13')));
    }

    public function test_a_bad_date_during_review_keeps_the_valid_deadline_and_sends_nothing(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind the top 3 defaulters to pay by 31 October'));
        $this->assertSame('ready', $ready['state']);

        $again = $flow->advance($this->context('make it 31 sept', $ready['pending']), $ready['pending']);

        $this->assertSame('ready', $again['state']);
        $this->assertStringContainsString('September has only 30 days', $again['message']);
        $this->assertStringContainsString('stays 2026-10-31', $again['message']);
        $this->assertSame('2026-10-31', $again['pending']['args']['deadline']);
        Mail::assertNothingSent();
    }

    /** @return array<int, array<string, mixed>> */
    private function history(): array
    {
        return [
            ['reminder_id' => 9, 'batch' => 'FR-20261013-newer', 'student_id' => 1, 'student_name' => 'ASHA RAO', 'channel' => 'sms', 'class' => 'XI',
                'message' => 'Dear ASHA RAO, pay Rs.335,480 by 31 October 2026.', 'outstanding' => 335480, 'deadline' => '2026-10-31', 'status' => 'sent',
                'detail' => 'Accepted by the SMS gateway (HTTP 200): {"status":"OK"}', 'sent_to' => '******3210', 'provider_id' => '3648178016739718',
                'delivery_status' => 'unknown', 'delivery_note' => 'The SMS gateway accepted it but sends no delivery receipt, so whether it reached the phone is unknown.',
                'recorded_at' => '2026-10-13 09:00:00'],
            ['reminder_id' => 7, 'batch' => 'FR-20261012-older', 'student_id' => 1, 'student_name' => 'ASHA RAO', 'channel' => 'sms', 'class' => 'XI',
                'message' => 'x', 'outstanding' => 335480, 'deadline' => '2026-10-31', 'status' => 'failed',
                'detail' => 'The SMS gateway replied with an error: Invalid API Key', 'sent_to' => '******3210', 'provider_id' => null,
                'delivery_status' => 'not_delivered', 'delivery_note' => 'The provider did not accept it, so it was not delivered.', 'recorded_at' => '2026-10-12 09:00:00'],
        ];
    }

    public function test_the_status_is_read_through_the_history_tool_so_the_source_is_recorded(): void
    {
        $this->historyRows = $this->history();
        $flow = $this->flow();

        $result = $flow->status($this->context('Show reminder status.'));

        $this->assertSame('status', $result['state']);
        $calls = array_values(array_filter($this->caller->log, fn ($entry) => $entry[0] === 'call'));
        $this->assertSame('fees.reminder_history', $calls[0][1]);
        $this->assertSame(2, $result['data']['count']);
        $this->assertSame(1, $result['data']['delivery']['unknown']);
        $this->assertSame(0, $result['data']['delivery']['confirmed_delivered']);
        $this->assertFalse($result['data']['asked_about_delivery']);
    }

    /** @return array<string, array{0:string}> */
    public static function deliveryQuestions(): array
    {
        return [
            'delivered' => ['Was the SMS delivered?'],
            'reach them' => ['Did the message reach them?'],
            'received' => ['Has the reminder been received?'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deliveryQuestions')]
    public function test_a_delivery_question_is_flagged_so_the_answer_says_what_the_records_can_and_cannot_show(string $question): void
    {
        $this->historyRows = $this->history();

        $result = $this->flow()->status($this->context($question));

        $this->assertTrue($result['data']['asked_about_delivery']);
        $this->assertSame('unknown', $result['data']['reminders'][0]['delivery_status']);
    }

    public function test_a_send_reference_in_the_question_reads_only_that_send(): void
    {
        $this->historyRows = $this->history();

        $result = $this->flow()->status($this->context('Show the status of FR-20261012-older'));

        $this->assertSame(1, $result['data']['count']);
        $this->assertSame('FR-20261012-older', $result['data']['reminders'][0]['batch']);
        $calls = array_values(array_filter($this->caller->log, fn ($entry) => $entry[0] === 'call'));
        $this->assertSame('FR-20261012-OLDER', $calls[0][2]['batch']);
    }

    // ------------------------------------------------------------------ WhatsApp in the conversation

    /** @return array<string, array{0:string, 1:?string}> */
    public static function channelSentences(): array
    {
        return [
            'prepare a whatsapp reminder' => ["Prepare a WhatsApp fee reminder for this student's parent.", 'whatsapp'],
            'by whatsapp' => ['Send it by WhatsApp.', 'whatsapp'],
            'whatsapp instead' => ['Send it on WhatsApp instead', 'whatsapp'],
            'sms instead of whatsapp' => ['Send it by SMS instead of WhatsApp.', 'sms'],
            'sms instead' => ['Send it by SMS instead', 'sms'],
            'plain sms' => ['Send it by SMS.', 'sms'],
            'email' => ['Send it by email.', 'email'],
            'none named' => ['By 31 October.', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('channelSentences')]
    public function test_the_channel_is_read_from_the_sentence_including_whatsapp(string $sentence, ?string $expected): void
    {
        $this->assertSame($expected, $this->flow()->parseRequest($sentence)['channel']);
    }

    public function test_the_whatsapp_request_keeps_the_student_and_the_deadline_already_given(): void
    {
        $flow = $this->flow();
        $sms = $flow->start($this->context('Remind student 1 by SMS to pay by 31 October'));
        $this->assertSame('ready', $sms['state']);
        $this->assertSame('sms', $sms['data']['channel']);

        // Nothing is asked again: the student, the amount and the deadline carry over to the WhatsApp preview.
        $wa = $flow->advance($this->context("Prepare a WhatsApp fee reminder for this student's parent.", $sms['pending']), $sms['pending']);

        $this->assertSame('ready', $wa['state']);
        $this->assertSame('whatsapp', $wa['data']['channel']);
        $this->assertSame('2026-10-31', $wa['data']['deadline']);
        $this->assertSame([1], $wa['pending']['args']['student_ids']);
        $this->assertSame('parents_', $wa['data']['template']['name']);
        $this->assertSame('******3210', $wa['data']['recipients'][0]['contact_masked']);
        $this->assertNotSame($sms['pending']['fingerprint'], $wa['pending']['fingerprint']);
        Mail::assertNothingSent();
    }

    public function test_yes_sends_the_whatsapp_reminder_and_reports_what_whatsapp_answered(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind student 1 by WhatsApp to pay by 31 October'));

        $sent = $flow->advance($this->context('Yes, send the reminders.', $ready['pending']), $ready['pending']);

        $this->assertSame('sent', $sent['state']);
        $this->assertSame('whatsapp', $sent['data']['channel']);
        $this->assertSame(1, $sent['data']['sent']);
        $this->assertStringContainsString('accepted by WhatsApp', $sent['message']);
    }

    public function test_a_sent_reminder_is_remembered_so_it_can_be_sent_again_on_another_channel(): void
    {
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind student 1 by SMS to pay by 31 October'));

        $sent = $flow->advance($this->context('Yes, send the reminders.', $ready['pending']), $ready['pending']);

        $this->assertSame('sent', $sent['state']);
        $this->assertNull($sent['pending']);
        $this->assertSame([1], $sent['remember']['args']['student_ids']);
        $this->assertSame('2026-10-31', $sent['remember']['args']['deadline']);
        $this->assertSame('sms', $sent['remember']['channel']);
        $this->assertNotEmpty($sent['remember']['reference']);
        $this->assertArrayNotHasKey('approved_fingerprint', $sent['remember']['args']);
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function resendSentences(): array
    {
        return [
            'whatsapp instead' => ['Send it by WhatsApp instead.', 'whatsapp'],
            'also on whatsapp' => ['Also send it on WhatsApp', 'whatsapp'],
            'sms as well' => ['Send it by SMS as well', 'sms'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('resendSentences')]
    public function test_send_it_by_another_channel_after_a_send_repeats_that_reminder_without_asking_again(string $sentence, string $channel): void
    {
        $flow = $this->flow();
        $memory = ['last_reminder' => ['args' => ['student_ids' => [1], 'deadline' => '2026-10-31', 'channel' => 'email'], 'reference' => 'FR-1', 'channel' => 'email']];

        $result = $flow->start($this->context($sentence, null, 'admin', $memory));

        $this->assertSame('ready', $result['state'], 'it must show the review, not ask for the student or the date again');
        $this->assertSame($channel, $result['data']['channel']);
        $this->assertSame('2026-10-31', $result['data']['deadline']);
        $this->assertSame([1], $result['pending']['args']['student_ids']);
        $this->assertSame(1, $result['data']['recipient_count']);
        $this->assertStringContainsString('same reminder as the one just sent', $result['message']);
        Mail::assertNothingSent();
    }

    public function test_a_channel_request_with_nothing_sent_before_is_not_treated_as_a_repeat(): void
    {
        $result = $this->flow()->start($this->context('Send it by WhatsApp instead.'));

        // Nothing was sent, so "it" names nothing: the flow asks, it does not invent a reminder.
        $this->assertNotSame('ready', $result['state']);
    }

    public function test_a_status_question_while_a_reminder_waits_is_answered_and_the_reminder_is_kept(): void
    {
        $this->historyRows = $this->history();
        $flow = $this->flow();
        $ready = $flow->start($this->context('Remind student 1 by SMS to pay by 31 October'));
        $this->assertSame('ready', $ready['state']);

        $context = $this->context('Show reminder status.', $ready['pending']);
        $context->intent = new \App\Domain\AI\Conversation\Intent('fees_reminder_status', 'Fee reminder status', 1.0);
        $context->plan = new \App\Domain\AI\Lifecycle\Plan\Plan(
            goal: 'x', steps: [], source: \App\Domain\AI\Lifecycle\Plan\Plan::SOURCE_DETERMINISTIC, route: 'fees_reminders_flow',
            intentKey: 'fees_reminder_status', candidateTools: ['fees.reminder_history'], toolSelectionStrategy: 'x', context: []
        );
        $context->selectedTools = ['fees.reminder_history'];

        $stage = new \App\Domain\AI\Lifecycle\Stages\LaravelMcpStage(
            $this->caller,
            $this->createMock(\App\Domain\AI\Lifecycle\Flows\AdmissionsFlow::class),
            $flow,
            $this->createMock(\App\Domain\AI\Lifecycle\Flows\FeesStudentDetailFlow::class),
            $this->createMock(\App\Domain\AI\Lifecycle\Flows\ParentMessageFlow::class),
        );
        $stage->run($context);

        $answered = $context->get('fees_reminders_flow');

        $this->assertSame('status', $answered['state']);
        $this->assertSame(2, $answered['data']['count']);
        // The waiting reminder is exactly as it was, so "yes" still approves the list that was on screen.
        $this->assertSame($ready['pending'], $answered['pending']);
        Mail::assertNothingSent();
    }

    public function test_when_the_history_cannot_be_read_nothing_is_claimed_about_what_was_sent(): void
    {
        $flow = $this->flow();
        $this->caller->unavailable = true;

        $result = $flow->status($this->context('Show reminder status.'));

        $this->assertSame('blocked', $result['state']);
        $this->assertStringContainsString('could not be read', $result['message']);
    }
}
