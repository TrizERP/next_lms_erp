<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Lifecycle\Flows\FeesRemindersFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Plan\DeterministicPlanner;
use App\Domain\AI\Lifecycle\Stages\ReasoningStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Services\Mcp\McpRequestContext;
use Tests\TestCase;

/**
 * How a fee reminder request is recognised, planned and put to the person.
 *
 * Nothing here reads or writes the database: sentences are classified, plans are built from a
 * module description, and the answer is composed from a flow result.
 */
class FeesReminderPlanningTest extends TestCase
{
    private function intentOf(string $sentence): string
    {
        return app(IntentClassifier::class)->classify($sentence)->key;
    }

    private function module(string $key, array $tools): ModuleCapability
    {
        return new ModuleCapability(key: $key, label: ucfirst($key), capabilities: ['conversational' => true], mcpTools: $tools);
    }

    private function context(string $question, ModuleCapability $module, ?string $intent = null, ?array $pending = null): StageContext
    {
        $context = new StageContext(
            question: $question,
            scope: new McpRequestContext(1, 'admin', 61, [61], null, null, 2026, null, true, false),
            module: $module,
        );

        if ($intent !== null) {
            $context->intent = app(IntentClassifier::class)->classify($question);
            $this->assertSame($intent, $context->intent->key, $question);
        }

        if ($pending !== null) {
            $context->thread = ['memory' => ['pending_action' => $pending]];
        }

        return $context;
    }

    private const FEES_TOOLS = ['fees.position', 'fees.outstanding_accounts', 'fees.send_reminders', 'students.search'];

    // ------------------------------------------------------------ recognising

    public function test_requests_to_send_or_notify_about_fees_are_the_reminder_action(): void
    {
        foreach ([
            'Send a fee reminder',
            'Send fee reminders to students with high outstanding fees',
            'Notify students with pending fees to pay by 31 October',
            'Remind the top 5 defaulters to pay by 2026-10-31',
            'Send payment reminders to parents of students who owe more than 50000',
            'Email the defaulters about their pending fees',
            'Notify students whose fees are outstanding',
            'Send a notification to students who owe fees',
        ] as $sentence) {
            $this->assertSame('fees_send_reminders', $this->intentOf($sentence), $sentence);
        }
    }

    public function test_questions_about_reminders_already_sent_are_the_status_read(): void
    {
        foreach ([
            'Were the fee reminders sent?',
            'Show reminder status',
            'Show the history of fee reminders sent',
            'Which reminders failed?',
        ] as $sentence) {
            $this->assertSame('fees_reminder_status', $this->intentOf($sentence), $sentence);
        }
    }

    public function test_ordinary_fee_questions_and_other_modules_notifications_are_not_captured(): void
    {
        foreach ([
            'Show students with pending fees' => 'fees_query',
            'Who owes the most fees?' => null,
            'Show the top 10 defaulters' => 'fees_query',
            'How much fee is pending?' => 'fees_query',
            'What is the total fee collection?' => 'fees_query',
            'Show students whose fees are overdue' => 'fees_query',
            'Fee collection report' => 'fees_query',
        ] as $sentence => $expected) {
            $intent = $this->intentOf($sentence);
            $this->assertNotContains($intent, ['fees_send_reminders', 'fees_reminder_status'], $sentence);

            if ($expected !== null) {
                $this->assertSame($expected, $intent, $sentence);
            }
        }

        // Notifications that name no fee are other modules' business and stay that way.
        foreach ([
            'Send a notification to parents about the holiday',
            'Notify parents about the PTM',
            'Remind me to review attendance',
            'Send an SMS to staff',
            'Show library books overdue',
            'Did the notifications go out?',
        ] as $sentence) {
            $this->assertNotContains($this->intentOf($sentence), ['fees_send_reminders', 'fees_reminder_status'], $sentence);
        }
    }

    // --------------------------------------------------------------- planning

    public function test_on_fees_the_request_is_planned_onto_the_reminder_flow_with_its_tools(): void
    {
        $context = $this->context('Remind the top 5 defaulters to pay by 31 October', $this->module('fees', self::FEES_TOOLS), 'fees_send_reminders');

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertNotNull($plan);
        $this->assertSame('fees_reminders_flow', $plan->route);
        $this->assertSame('fees_reminders_flow', $plan->toolSelectionStrategy);
        $this->assertEqualsCanonicalizing(['fees.send_reminders', 'fees.outstanding_accounts', 'students.search'], $plan->candidateTools);
    }

    public function test_questions_about_pending_fees_per_student_are_planned_onto_the_student_route(): void
    {
        $tools = array_merge(self::FEES_TOOLS, ['fees.getPending']);

        foreach (['Show students with pending fees', 'Which students owe the most fees?', 'Which students currently have pending or unpaid fees?'] as $sentence) {
            $plan = app(DeterministicPlanner::class)->plan($this->context($sentence, $this->module('fees', $tools), 'fees_query'));

            $this->assertNotNull($plan, $sentence);
            $this->assertSame('fees_student_flow', $plan->route, $sentence);
        }

        // Class-wise and summary questions are not redirected.
        foreach (['Show the fee summary', 'Show classes with most pending fees', 'How many students have pending fees?'] as $sentence) {
            $plan = app(DeterministicPlanner::class)->plan($this->context($sentence, $this->module('fees', $tools), 'fees_query'));

            $this->assertTrue($plan === null || $plan->route !== 'fees_student_flow', $sentence);
        }
    }

    public function test_a_question_the_classifier_has_no_word_for_still_reaches_the_student_list_on_fees(): void
    {
        // "the list of students who have pending dues" — said in Gujarati and translated — names no
        // fee noun, so the classifier calls it unknown. On Fees it is still a request for students.
        $tools = array_merge(self::FEES_TOOLS, ['fees.getPending']);
        $context = $this->context('Show the list of students who have pending dues.', $this->module('fees', $tools));
        $context->intent = \App\Domain\AI\Conversation\Intent::unknown();

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertNotNull($plan);
        $this->assertSame('fees_student_flow', $plan->route);

        // Another module's page is left to the model planner, as before.
        $other = $this->context('Show the list of students who have pending dues.', $this->module('student', ['students.search']));
        $other->intent = \App\Domain\AI\Conversation\Intent::unknown();
        $this->assertNull(app(DeterministicPlanner::class)->plan($other));

        // And an unknown sentence that is not about students owing is untouched.
        $unrelated = $this->context('Tell me a joke', $this->module('fees', $tools));
        $unrelated->intent = \App\Domain\AI\Conversation\Intent::unknown();
        $this->assertNull(app(DeterministicPlanner::class)->plan($unrelated));
    }

    public function test_the_students_fee_detail_after_a_class_is_planned_onto_the_student_route(): void
    {
        $tools = array_merge(self::FEES_TOOLS, ['fees.getPending']);
        $context = $this->context("Show me the student's fee detail", $this->module('fees', $tools));
        $class = ['standard_id' => 6, 'standard_name' => 'Class 6'];
        $context->intent = new \App\Domain\AI\Conversation\Intent('record_detail', 'Show one record', 1.0, ['record_id' => 6, 'record_id_field' => 'standard_id']);
        $context->thread = ['memory' => ['selected_record' => ['item' => ['id' => 6, 'title' => 'Class 6', 'row' => $class], 'singular' => 'class', 'module' => 'fees']]];

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertNotNull($plan);
        $this->assertSame('fees_student_flow', $plan->route);
    }
    public function test_on_any_other_module_the_sentence_is_left_to_the_model_planner_as_before(): void
    {
        foreach (['library', 'communication', 'consent', 'petty_cash'] as $key) {
            $context = $this->context('Send fee reminders to students with high outstanding fees', $this->module($key, ['library.catalogue']));
            $context->intent = app(IntentClassifier::class)->classify($context->question);

            $this->assertNull(app(DeterministicPlanner::class)->plan($context), $key);
        }
    }

    public function test_a_reminder_in_progress_outranks_the_next_sentence_on_fees_only(): void
    {
        $pending = ['kind' => FeesRemindersFlow::KIND, 'state' => 'need_deadline', 'args' => ['top' => 5]];

        // "by 31 October" classifies as nothing at all; read against the pending task it is the answer.
        $fees = $this->context('by 31 October', $this->module('fees', self::FEES_TOOLS), null, $pending);
        $plan = app(DeterministicPlanner::class)->plan($fees);

        $this->assertNotNull($plan);
        $this->assertSame('fees_reminders_flow', $plan->route);

        // The same pending state on another module's page does not hijack that module.
        $other = $this->context('by 31 October', $this->module('library', ['library.catalogue']), null, $pending);
        $this->assertNull(app(DeterministicPlanner::class)->plan($other));
    }

    public function test_the_status_question_reaches_the_flow_without_needing_a_tool_of_its_own(): void
    {
        $context = $this->context('Were the fee reminders sent?', $this->module('fees', self::FEES_TOOLS), 'fees_reminder_status');

        $plan = app(DeterministicPlanner::class)->plan($context);

        $this->assertSame('fees_reminders_flow', $plan->route);
        $this->assertNotSame([], $plan->candidateTools);
    }

    // -------------------------------------------------------------- the answer

    /** @param  array<string, mixed>  $flow */
    private function render(array $flow): StageContext
    {
        $context = $this->context('x', $this->module('fees', self::FEES_TOOLS));
        $context->set('fees_reminders_flow', $flow);

        app(ReasoningStage::class)->run($context);

        return $context;
    }

    private function allText(StageContext $context): string
    {
        return json_encode([$context->headline(), $context->sections(), $context->actions(), $context->followUps()], JSON_UNESCAPED_UNICODE);
    }

    public function test_the_review_shows_every_recipient_amount_deadline_message_and_an_approve_button(): void
    {
        $context = $this->render([
            'state' => 'ready',
            'message' => '',
            'data' => [
                'selection' => 'The 2 students who owe the most',
                'deadline' => '2026-10-31',
                'deadline_label' => '31 October 2026',
                'recipient_count' => 2,
                'total_outstanding' => 339980,
                'recipients' => [
                    ['student_id' => 1, 'name' => 'ASHA RAO', 'class' => 'XI', 'outstanding' => 335480, 'outstanding_label' => '₹3,35,480', 'contact_masked' => 'a***@example.com'],
                    ['student_id' => 4, 'name' => 'DIVYA IYER', 'class' => 'X', 'outstanding' => 4500, 'outstanding_label' => '₹4,500', 'contact_masked' => 'd***@example.com'],
                ],
                'unreachable' => [['student_id' => 2, 'name' => 'BINA SHAH', 'class' => 'IX', 'outstanding' => 271440, 'reason' => 'No email address on the student record.']],
                'sample_subject' => 'Fee payment reminder',
                'sample_message' => "Dear ASHA RAO,\n\nYour outstanding fee amount is ₹3,35,480. Please submit your pending fees by 31 October 2026.",
            ],
            'pending' => ['kind' => FeesRemindersFlow::KIND, 'state' => 'ready', 'args' => [], 'fingerprint' => 'abc'],
        ]);

        $text = $this->allText($context);

        $this->assertStringContainsString('Review these 2 email reminders before anything is sent', (string) $context->headline());
        foreach (['ASHA RAO', 'DIVYA IYER', '₹3,35,480', '₹4,500', '31 October 2026', 'a***@example.com', 'BINA SHAH', 'No email address on the student record.',
            'Your outstanding fee amount is ₹3,35,480. Please submit your pending fees by 31 October 2026.', 'Nothing has been sent', '₹3,39,980'] as $expected) {
            $this->assertStringContainsString($expected, $text, $expected);
        }
        $this->assertSame('fees_send_reminders', $context->actions()[0]['intent'] ?? null);
        $this->assertSame('Yes, send the reminders.', $context->actions()[0]['utterance'] ?? null);
        $this->assertSame(['kind' => FeesRemindersFlow::KIND, 'state' => 'ready', 'args' => [], 'fingerprint' => 'abc'], $context->get('pending_action_next'));
    }

    public function test_the_result_says_accepted_not_delivered_and_names_each_outcome(): void
    {
        $context = $this->render([
            'state' => 'sent',
            'message' => '1 reminder accepted by the mail server, 1 failed, 1 skipped (no email on record).',
            'data' => [
                'reference' => 'FR-20261013-abcde', 'deadline' => '2026-10-31', 'sent' => 1, 'failed' => 1, 'skipped' => 1,
                'results' => [
                    ['student_id' => 1, 'name' => 'ASHA RAO', 'outstanding' => 335480, 'status' => 'sent', 'detail' => 'Accepted by the mail server.'],
                    ['student_id' => 3, 'name' => 'CHIRAG DESAI', 'outstanding' => 120000, 'status' => 'failed', 'detail' => 'The mail server did not accept it: refused'],
                    ['student_id' => 2, 'name' => 'BINA SHAH', 'outstanding' => 271440, 'status' => 'skipped', 'detail' => 'No email address on the student record.'],
                ],
            ],
            'pending' => null,
        ]);

        $text = $this->allText($context);

        foreach (['Accepted by the mail server', 'Failed', 'Not contacted', 'FR-20261013-abcde', 'not proof it reached an inbox', 'refused'] as $expected) {
            $this->assertStringContainsString($expected, $text, $expected);
        }
        $this->assertStringNotContainsStringIgnoringCase('delivered to', $text);
        $this->assertNull($context->get('pending_action_next'));
        $this->assertSame([], $context->actions(), 'a finished send must not offer to send again');
    }

    public function test_when_nobody_has_an_email_the_answer_says_what_would_be_needed(): void
    {
        $text = $this->allText($this->render([
            'state' => 'nothing_to_send',
            'message' => 'None of these students has an email address on record, so nobody can be reminded.',
            'data' => ['unreachable' => [['name' => 'BINA SHAH', 'class' => 'IX', 'outstanding' => 271440, 'reason' => 'No email address on the student record.']]],
            'pending' => null,
        ]));

        $this->assertStringContainsString('Nobody on this list can be reached by email', $text);
        $this->assertStringContainsString('WhatsApp and app notifications are not available', $text);
        $this->assertStringContainsString('BINA SHAH', $text);
    }

    public function test_asking_for_the_deadline_shows_who_would_be_reminded_and_sends_nothing(): void
    {
        $context = $this->render([
            'state' => 'need_deadline',
            'message' => 'By what date should they pay?',
            'data' => [
                'selection' => 'The 2 students who owe the most',
                'accounts' => [
                    ['student_id' => 1, 'student_name' => 'ASHA RAO', 'standard_name' => 'XI', 'outstanding' => 335480, 'outstanding_label' => '₹3,35,480'],
                ],
            ],
            'pending' => ['kind' => FeesRemindersFlow::KIND, 'state' => 'need_deadline', 'args' => ['top' => 2]],
        ]);

        $text = $this->allText($context);

        $this->assertStringContainsString('By what date should they pay?', $text);
        $this->assertStringContainsString('ASHA RAO', $text);
        $this->assertStringContainsString('nothing is sent until you approve it', $text);
        $this->assertSame([], $context->actions());
    }
}
