<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Flows\ParentMessageFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ParentMessageService;
use App\Services\Mcp\ToolResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The chat conversation around sending a parent message: what is read from a sentence, what asks a
 * question, and — above all — that only a plain "yes" to the preview on screen can send anything.
 *
 * NO NETWORK, NO DATABASE: the service is replaced, so nothing is previewed against real students and
 * nothing is sent. The cases here name no class or student, so the flow never queries.
 */
class ParentMessageFlowTest extends TestCase
{
    private function scope(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    private function context(string $question): StageContext
    {
        return new StageContext($question, $this->scope(), ModuleCapability::general());
    }

    /** @return array{0:ParentMessageFlow, 1:MockObject&ParentMessageService} */
    private function flow(bool $administrator = true): array
    {
        $service = $this->createMock(ParentMessageService::class);
        $admins = $this->createMock(AdministratorCheck::class);
        $admins->method('isAdministrator')->willReturn($administrator);

        return [new ParentMessageFlow($service, $admins), $service];
    }

    /** @return array<string, mixed> */
    private function ready(): array
    {
        return [
            'kind' => ParentMessageFlow::KIND,
            'state' => 'ready',
            'args' => ['channel' => 'sms', 'message' => 'Closed tomorrow', 'student_ids' => [1, 2], 'standard_id' => null, 'division_id' => null, 'target_label' => 'Class 10 A'],
            'fingerprint' => 'abc123',
        ];
    }

    private function preview(): array
    {
        return ToolResult::success('communication.send_parent_message', '2 messages ready.', [
            'channel' => 'whatsapp', 'recipient_count' => 2, 'fingerprint' => 'new-fingerprint', 'recipients' => [], 'unreachable' => [],
        ]);
    }

    /** @return array<string, array{0:string, 1:?string, 2:?string, 3:?string, 4:?string}> */
    public static function sentences(): array
    {
        return [
            'class and division' => ['Send an SMS to the parents of class 10 A saying school is closed tomorrow.', 'sms', 'school is closed tomorrow', '10', 'A'],
            'whatsapp, quoted wording' => ['WhatsApp the parents of standard 8 "Exam starts Monday"', 'whatsapp', 'Exam starts Monday', '8', null],
            'article is not a division' => ['text the parents of grade 9 a reminder about the picnic', 'sms', null, '9', null],
            'dash division' => ['Message class 7-B parents by sms: PTM on Friday', 'sms', 'PTM on Friday', '7', 'B'],
            'no class, no channel' => ['Inform the parents about the holiday', null, null, null, null],
        ];
    }

    #[DataProvider('sentences')]
    public function test_a_sentence_is_read_for_channel_wording_and_class(string $text, ?string $channel, ?string $message, ?string $class, ?string $division): void
    {
        $read = ParentMessageFlow::parse($text);

        $this->assertSame($channel, $read['channel']);
        $this->assertSame($message, $read['message']);
        $this->assertSame($class, $read['class_number']);
        $this->assertSame($division, $read['division']);
    }

    public function test_a_named_student_is_read_from_the_parent_of_phrase(): void
    {
        $read = ParentMessageFlow::parse('WhatsApp the parent of Aarav Shah saying he may collect his result');

        $this->assertSame('Aarav Shah', $read['student_name']);
        $this->assertSame('whatsapp', $read['channel']);
        $this->assertSame('he may collect his result', $read['message']);
    }

    public function test_it_asks_for_each_missing_detail_before_previewing(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('preview');
        $service->expects($this->never())->method('send');

        $first = $flow->start($this->context('Inform the parents about the holiday'));
        $this->assertSame('need_channel', $first['state']);

        $next = $flow->advance($this->context('By WhatsApp.'), $first['pending']);
        $this->assertSame('need_recipients', $next['state']);
        $this->assertSame('whatsapp', $next['pending']['args']['channel']);
    }

    public function test_a_person_who_is_not_an_administrator_is_refused_up_front(): void
    {
        [$flow, $service] = $this->flow(false);
        $service->expects($this->never())->method('preview');

        $result = $flow->start($this->context('Send an SMS to the parents of class 10 saying hello'));

        $this->assertSame('blocked', $result['state']);
        $this->assertSame(ParentMessageFlow::NOT_AN_ADMINISTRATOR, $result['message']);
        $this->assertNull($result['pending']);
    }

    public function test_only_a_plain_yes_to_the_preview_sends_and_it_sends_that_exact_list(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('preview');
        $service->expects($this->once())->method('send')
            ->with($this->anything(), $this->callback(fn (array $args) => $args['channel'] === 'sms' && $args['message'] === 'Closed tomorrow' && $args['student_ids'] === [1, 2]), 'abc123')
            ->willReturn(ToolResult::success('communication.send_parent_message', 'Sent.', ['sent' => 2, 'channel' => 'sms']));

        $result = $flow->advance($this->context('Yes, send them.'), $this->ready());

        $this->assertSame('sent', $result['state']);
        $this->assertNull($result['pending']);
    }

    /** @return array<string, array{0:string}> */
    public static function notApprovals(): array
    {
        return [
            'a new channel' => ['Send it by WhatsApp instead.'],
            'new wording' => ['Make it "School reopens on Monday"'],
            'a number' => ['Yes, but only 3 of them'],
            'chatter' => ['What will it say?'],
        ];
    }

    #[DataProvider('notApprovals')]
    public function test_an_edit_is_never_an_approval_and_gets_a_fresh_preview(string $reply): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');
        $service->method('preview')->willReturn($this->preview());

        $result = $flow->advance($this->context($reply), $this->ready());

        $this->assertNotSame('sent', $result['state']);
    }

    public function test_changing_the_channel_previews_again_with_the_new_channel(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');
        $service->expects($this->once())->method('preview')
            ->with($this->anything(), $this->callback(fn (array $args) => $args['channel'] === 'whatsapp' && $args['message'] === 'Closed tomorrow'))
            ->willReturn($this->preview());

        $result = $flow->advance($this->context('Send it by WhatsApp instead.'), $this->ready());

        $this->assertSame('ready', $result['state']);
        $this->assertSame('new-fingerprint', $result['pending']['fingerprint']);
        $this->assertSame('whatsapp', $result['pending']['args']['channel']);
    }

    public function test_a_yes_when_nothing_is_ready_does_not_send(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_message', 'args' => ['channel' => 'sms', 'student_ids' => [1], 'standard_id' => null, 'division_id' => null]];
        $service->method('preview')->willReturn($this->preview());

        $result = $flow->advance($this->context('Yes'), $pending);

        // "Yes" is taken as the wording the user was asked for, and the result is a preview, not a send.
        $this->assertSame('ready', $result['state']);
    }

    public function test_a_refused_send_leaves_nothing_pending(): void
    {
        [$flow, $service] = $this->flow();
        $service->method('send')->willReturn(ToolResult::failure('communication.send_parent_message', 'The list changed.', 'recipients_changed', ['sent' => 0]));

        $result = $flow->advance($this->context('Yes'), $this->ready());

        $this->assertSame('failed', $result['state']);
        $this->assertNull($result['pending']);
    }

    public function test_the_two_options_by_name_set_the_channel(): void
    {
        [$flow] = $this->flow();

        $this->assertSame('sms', $flow->start($this->context('Send SMS'))['pending']['args']['channel']);
        $this->assertSame('whatsapp', $flow->start($this->context('Send WhatsApp Message'))['pending']['args']['channel']);
        $this->assertSame('need_recipients', $flow->start($this->context('Send SMS'))['state']);
    }

    public function test_once_the_student_and_channel_are_known_the_template_is_shown_before_the_wording_is_asked_for(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('preview');
        $service->expects($this->once())->method('describe')
            ->with($this->anything(), $this->callback(fn (array $a) => $a['channel'] === 'sms' && $a['student_ids'] === [7]))
            ->willReturn(ToolResult::success('communication.send_parent_message', 'loaded', ['students' => [['name' => 'Asha']], 'template' => ['kind' => 'signature', 'text' => '.Sig']]));

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_channel', 'args' => ['channel' => null, 'message' => null, 'standard_id' => null, 'division_id' => null, 'student_ids' => [7], 'target_label' => 'Asha']];
        $result = $flow->advance($this->context('Send SMS'), $pending);

        $this->assertSame('need_message', $result['state']);
        $this->assertSame('.Sig', $result['data']['details']['template']['text']);
    }

    public function test_an_unconfigured_channel_is_reported_before_the_person_types_a_message(): void
    {
        [$flow, $service] = $this->flow();
        $service->method('describe')->willReturn(ToolResult::failure('communication.send_parent_message', 'WhatsApp is not configured for this institute.', 'channel_not_configured'));

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_channel', 'args' => ['channel' => null, 'message' => null, 'standard_id' => null, 'division_id' => null, 'student_ids' => [7], 'target_label' => 'Asha']];
        $result = $flow->advance($this->context('Send WhatsApp Message'), $pending);

        $this->assertSame('failed', $result['state']);
        $this->assertNull($result['pending']);
    }

    public function test_the_wording_is_taken_whole_even_when_it_mentions_a_channel_class_or_name(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');
        $service->expects($this->once())->method('preview')
            ->with($this->anything(), $this->callback(fn (array $a) => $a['channel'] === 'sms' && $a['message'] === 'Bring the WhatsApp consent form for class 9 to Aarav Shah on Monday' && $a['student_ids'] === [7]))
            ->willReturn($this->preview());

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_message', 'args' => ['channel' => 'sms', 'message' => null, 'standard_id' => null, 'division_id' => null, 'student_ids' => [7], 'target_label' => 'Asha']];
        $result = $flow->advance($this->context('Bring the WhatsApp consent form for class 9 to Aarav Shah on Monday'), $pending);

        $this->assertSame('ready', $result['state']);
    }

    public function test_a_reply_that_is_only_a_channel_changes_the_channel_not_the_wording(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('preview');
        $service->method('describe')->willReturn(ToolResult::success('communication.send_parent_message', 'ok', ['students' => [], 'template' => []]));

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_message', 'args' => ['channel' => 'sms', 'message' => null, 'standard_id' => null, 'division_id' => null, 'student_ids' => [7], 'target_label' => 'Asha']];
        $result = $flow->advance($this->context('by WhatsApp instead'), $pending);

        $this->assertSame('need_message', $result['state']);
        $this->assertSame('whatsapp', $result['pending']['args']['channel']);
    }

    public function test_cancel_is_recognised_generously_and_approval_strictly(): void
    {
        [$flow] = $this->flow();

        foreach (['Cancel.', 'never mind', 'stop', "don't send", 'No'] as $text) {
            $this->assertTrue($flow->looksLikeCancel($text), $text);
        }

        foreach (['Yes', 'yes, send them', 'Go ahead', 'send it'] as $text) {
            $this->assertTrue($flow->looksLikeApproval($text), $text);
        }

        foreach (['yes but by whatsapp', 'send it to class 9', 'Yes, send to class 9 as well', 'yes, send them to only the top 3', 'ok make it shorter', 'yes, cancel that'] as $text) {
            $this->assertFalse($flow->looksLikeApproval($text), $text);
        }
    }

    /** @return array<string, array{0:string, 1:?string}> */
    public static function bothSentences(): array
    {
        return [
            'and' => ['Send SMS and WhatsApp to the parents of class 10 A saying hello', 'both'],
            'reversed' => ['WhatsApp and SMS the parents of class 8 "Exam Monday"', 'both'],
            'by both' => ['Message the parents of class 7 B by both: PTM on Friday', 'both'],
            'plus' => ['send sms + whatsapp to the parent of Aarav Shah', 'both'],
            // The other channel's name inside the wording is wording, not a request for it.
            'wording mentions sms' => ['WhatsApp the parents of class 9 saying check your SMS for the code', 'whatsapp'],
            'quoted wording mentions whatsapp' => ['Send an SMS to class 9 parents "join the WhatsApp group"', 'sms'],
        ];
    }

    #[DataProvider('bothSentences')]
    public function test_both_channels_are_read_from_the_request_not_the_wording(string $text, string $channel): void
    {
        $this->assertSame($channel, ParentMessageFlow::parse($text)['channel']);
    }

    public function test_the_third_option_by_name_and_a_channel_only_reply_set_both(): void
    {
        [$flow] = $this->flow();

        $this->assertSame('both', $flow->start($this->context('Send SMS and WhatsApp'))['pending']['args']['channel']);

        $pending = ['kind' => ParentMessageFlow::KIND, 'state' => 'need_channel', 'args' => ['channel' => null, 'message' => null, 'standard_id' => null, 'division_id' => null, 'student_ids' => [], 'target_label' => null]];
        $this->assertSame('both', $flow->advance($this->context('both'), $pending)['pending']['args']['channel']);
        $this->assertFalse($flow->looksLikeApproval('send it by both'));
    }

    private function bothReady(): array
    {
        return [
            'kind' => ParentMessageFlow::KIND,
            'state' => 'ready',
            'args' => ['channel' => 'both', 'message' => 'Closed tomorrow', 'student_ids' => [1, 2], 'standard_id' => null, 'division_id' => null, 'target_label' => 'Class 10 A'],
            'fingerprint' => json_encode(['sms' => 'fp-sms', 'whatsapp' => 'fp-wa']),
        ];
    }

    private function channelPreview(string $channel, ?string $fingerprint = 'fp'): array
    {
        return ToolResult::success('communication.send_parent_message', '2 ready.', [
            'channel' => $channel, 'channel_label' => $channel === 'sms' ? 'SMS' : 'WhatsApp', 'recipient_count' => 2,
            'fingerprint' => $fingerprint, 'recipients' => [], 'unreachable' => [], 'warnings' => [],
        ]);
    }

    public function test_both_previews_each_channel_separately_and_the_approval_carries_both_fingerprints(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');
        $seen = [];
        $service->expects($this->exactly(2))->method('preview')->willReturnCallback(function ($scope, array $args) use (&$seen) {
            $seen[] = $args['channel'];

            return $this->channelPreview($args['channel'], 'fp-'.$args['channel']);
        });

        $result = $flow->advance($this->context('Send it by both'), $this->ready());

        $this->assertSame(['sms', 'whatsapp'], $seen);
        $this->assertSame('ready', $result['state']);
        $this->assertSame(['sms' => 'fp-sms', 'whatsapp' => 'fp-whatsapp'], json_decode($result['pending']['fingerprint'], true));
        $this->assertSame('both', $result['pending']['args']['channel']);
    }

    public function test_an_unconfigured_channel_is_named_and_the_other_is_still_previewed(): void
    {
        [$flow, $service] = $this->flow();
        $service->method('preview')->willReturnCallback(fn ($scope, array $args) => $args['channel'] === 'whatsapp'
            ? ToolResult::failure('communication.send_parent_message', 'WhatsApp is not configured for this institute.', 'channel_not_configured')
            : $this->channelPreview('sms', 'fp-sms'));

        $result = $flow->advance($this->context('Send it by both'), $this->ready());

        $this->assertSame('ready', $result['state']);
        $this->assertSame(['sms' => 'fp-sms'], json_decode($result['pending']['fingerprint'], true));
        $this->assertStringContainsString('WhatsApp will NOT be sent', $result['message']);
        $this->assertArrayHasKey('whatsapp', $result['pending']['unavailable']);
    }

    public function test_both_unavailable_is_a_failure_that_sends_nothing(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->never())->method('send');
        $service->method('preview')->willReturn(ToolResult::failure('communication.send_parent_message', 'Not configured.', 'channel_not_configured'));

        $result = $flow->advance($this->context('Send it by both'), $this->ready());

        $this->assertSame('failed', $result['state']);
        $this->assertNull($result['pending']);
    }

    public function test_approving_both_sends_each_channel_with_its_own_fingerprint(): void
    {
        [$flow, $service] = $this->flow();
        $calls = [];
        $service->expects($this->exactly(2))->method('send')->willReturnCallback(function ($scope, array $args, $fingerprint) use (&$calls) {
            $calls[$args['channel']] = $fingerprint;

            return ToolResult::success('communication.send_parent_message', '2 accepted.', ['reference' => 'PM-'.$args['channel'], 'sent' => 2, 'failed' => 0, 'skipped' => 0, 'results' => [['student_id' => 1, 'name' => 'Asha', 'status' => 'sent', 'detail' => 'ok', 'provider_id' => 'x']]]);
        });

        $result = $flow->advance($this->context('Yes, send them.'), $this->bothReady());

        $this->assertSame(['sms' => 'fp-sms', 'whatsapp' => 'fp-wa'], $calls);
        $this->assertSame('sent', $result['state']);
        $this->assertSame(4, $result['data']['sent']);
        $this->assertSame('both', $result['data']['channel']);
        $this->assertNull($result['pending']);
    }

    public function test_one_channel_failing_or_throwing_never_stops_the_other(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->exactly(2))->method('send')->willReturnCallback(function ($scope, array $args) {
            if ($args['channel'] === 'sms') {
                throw new \RuntimeException('gateway exploded');
            }

            return ToolResult::success('communication.send_parent_message', '1 accepted.', ['reference' => 'PM-wa', 'sent' => 1, 'failed' => 0, 'skipped' => 0, 'results' => []]);
        });

        $result = $flow->advance($this->context('Yes'), $this->bothReady());

        $this->assertSame('sent', $result['state'], 'WhatsApp went out, so the turn is not reported as a total failure');
        $this->assertFalse($result['data']['channels']['sms']['ok']);
        $this->assertTrue($result['data']['channels']['whatsapp']['ok']);
        $this->assertStringContainsString('gateway exploded', $result['message']);
    }

    public function test_both_failing_is_reported_as_failed(): void
    {
        [$flow, $service] = $this->flow();
        $service->method('send')->willReturn(ToolResult::failure('communication.send_parent_message', 'The list changed.', 'recipients_changed', ['sent' => 0]));

        $result = $flow->advance($this->context('Yes'), $this->bothReady());

        $this->assertSame('failed', $result['state']);
        $this->assertSame(0, $result['data']['sent']);
    }

    public function test_a_channel_missing_from_the_approval_is_not_sent_and_is_explained(): void
    {
        [$flow, $service] = $this->flow();
        $service->expects($this->once())->method('send')->with($this->anything(), $this->callback(fn (array $a) => $a['channel'] === 'sms'), 'fp-sms')
            ->willReturn(ToolResult::success('communication.send_parent_message', 'ok', ['sent' => 1, 'failed' => 0, 'skipped' => 0, 'results' => []]));

        $pending = $this->bothReady();
        $pending['fingerprint'] = json_encode(['sms' => 'fp-sms']);
        $pending['unavailable'] = ['whatsapp' => 'WhatsApp is not configured for this institute.'];

        $result = $flow->advance($this->context('Yes'), $pending);

        $this->assertSame('sent', $result['state']);
        $this->assertStringContainsString('WhatsApp was not sent: WhatsApp is not configured', $result['message']);
    }
}

