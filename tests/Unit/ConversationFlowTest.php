<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\FlowTrace;
use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Conversation\TraceStage;
use PHPUnit\Framework\TestCase;

/**
 * The conversational layer: understanding a question, and reporting what ran.
 *
 * Framework-free, like GovernanceKernelTest and PageContextTest, so these run without
 * booting the application.
 *
 * Two things are worth asserting here and nowhere else. First, that rephrasing a
 * question does not change what the system does — the routing decides whether an agent
 * runs against real records and whether an approval is recorded, so it must not drift
 * with wording. Second, that the trace reports the stages that did *not* run, since a
 * pipeline view that only shows what fired teaches nobody how the pipeline is wired.
 */
class ConversationFlowTest extends TestCase
{
    private IntentClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new IntentClassifier();
    }

    /**
     * The same request, said eight ways, must reach the same intent. This is the test
     * the brief asks for directly: "change the wording".
     */
    public function test_rephrasing_a_question_reaches_the_same_intent(): void
    {
        $phrasings = [
            'student_risk_scan' => [
                'Which students are at academic risk?',
                'Find students at academic risk.',
                'show me at-risk kids',
                'who is struggling in my class',
                'Any children falling behind?',
                'list underperforming learners',
            ],
            'evidence_inspect' => [
                'What evidence supports this?',
                'how do you know that',
                'what data backs this up',
                'where did this come from',
            ],
            'recommendation_advice' => [
                'What should the teacher do?',
                'what are the next steps',
                'how can we help him',
                'any recommendation for this student',
            ],
            'outcome_status' => [
                'Did the intervention work?',
                'has he improved',
                'show the before and after',
            ],
        ];

        foreach ($phrasings as $expected => $utterances) {
            foreach ($utterances as $utterance) {
                $this->assertSame(
                    $expected,
                    $this->classifier->classify($utterance)->key,
                    sprintf('"%s" should be understood as %s', $utterance, $expected)
                );
            }
        }
    }

    /**
     * A question the module cannot answer must run nothing at all. Routing a
     * half-understood sentence would mean analysing, or approving, against the wrong
     * record — worse than admitting the miss.
     */
    public function test_an_unrecognised_question_is_refused_rather_than_guessed(): void
    {
        $intent = $this->classifier->classify('what is the weather today');

        $this->assertTrue($intent->isUnknown());
        $this->assertSame(0.0, $intent->confidence);
        $this->assertNotEmpty($intent->suggestions, 'A refusal should still say what can be asked.');
    }

    /**
     * "approve" is a command even when the sentence is full of words that belong to
     * other intents. Without the leading-verb rule, "approve the workflow step" scores
     * as a status enquiry, because `workflow` and `step` are heavy words there.
     */
    public function test_a_leading_imperative_beats_a_topical_word(): void
    {
        $this->assertSame('approve_recommendation', $this->classifier->classify('Approve the workflow step.')->key);
        $this->assertSame('approve_recommendation', $this->classifier->classify('Approve the recommendation.')->key);
        $this->assertSame('reject_recommendation', $this->classifier->classify('Reject the drafted activities.')->key);
        $this->assertSame('workflow_status', $this->classifier->classify('What is the workflow status?')->key);
    }

    /**
     * A name must be pulled out of the sentence, and must stop at the first lowercase
     * word — otherwise "Why is Ravi Kumar at risk?" yields a student called
     * "Ravi Kumar at risk", which then matches nobody.
     */
    public function test_a_student_name_is_extracted_without_swallowing_the_rest_of_the_sentence(): void
    {
        $intent = $this->classifier->classify('Why is Ravi Kumar at risk?');

        $this->assertSame('student_risk_explain', $intent->key);
        $this->assertSame('Ravi Kumar', $intent->slot('student_name'));
    }

    /**
     * A pronoun must not become a name.
     */
    public function test_a_pronoun_is_not_mistaken_for_a_name(): void
    {
        $intent = $this->classifier->classify('why is she at risk');

        $this->assertSame('student_risk_explain', $intent->key);
        $this->assertNull($intent->slot('student_name'));
    }

    /**
     * "Student A" is a position in the previous answer, not somebody called A.
     */
    public function test_student_a_is_a_position_not_a_name(): void
    {
        $intent = $this->classifier->classify('Why is Student A at risk?');

        $this->assertSame('Student A', $intent->slot('student_label'));
        $this->assertNull($intent->slot('student_name'));
    }

    /**
     * Explicit ids in the sentence are honoured, so a button and a typed sentence can
     * share one code path.
     */
    public function test_explicit_references_are_extracted(): void
    {
        $slots = $this->classifier->classify('What evidence supports case 12?')->slots;
        $this->assertSame(12, $slots['case_id']);

        $slots = $this->classifier->classify('Approve recommendation 7')->slots;
        $this->assertSame(7, $slots['recommendation_id']);

        $slots = $this->classifier->classify('Show me CASE-2026-000001')->slots;
        $this->assertSame('CASE-2026-000001', $slots['case_reference']);
    }

    // ------------------------------------------------------------------ memory

    /**
     * A follow-up question inherits its subject from the thread — and the inheritance
     * is reported, so a user can see when the system filled a gap on their behalf.
     */
    public function test_a_follow_up_inherits_its_subject_and_says_so(): void
    {
        $store = new ConversationStore();
        $intent = $this->classifier->classify('What evidence supports this?');

        $this->assertNull($intent->slot('case_id'));

        [$resolved, $inherited] = $store->resolveReferents($intent, ['case_id' => 42, 'student_id' => 7]);

        $this->assertSame(42, $resolved->slot('case_id'));
        $this->assertArrayHasKey('case_id', $inherited, 'Inherited slots must be reportable.');
    }

    /**
     * What the sentence says always beats what the thread remembers. Otherwise asking
     * about a second student would silently answer about the first.
     */
    public function test_the_sentence_overrides_memory(): void
    {
        $store = new ConversationStore();
        $intent = $this->classifier->classify('What evidence supports case 99?');

        [$resolved, $inherited] = $store->resolveReferents($intent, ['case_id' => 42]);

        $this->assertSame(99, $resolved->slot('case_id'));
        $this->assertArrayNotHasKey('case_id', $inherited);
    }

    /**
     * "Student A" resolves against the list the previous answer showed.
     */
    public function test_a_positional_label_resolves_against_the_last_answer(): void
    {
        $store = new ConversationStore();
        $intent = $this->classifier->classify('Why is Student B at risk?');

        [$resolved] = $store->resolveReferents($intent, [
            'last_case_list' => [
                ['case_id' => 1, 'student_id' => 11, 'student_name' => 'First'],
                ['case_id' => 2, 'student_id' => 22, 'student_name' => 'Second'],
            ],
        ]);

        $this->assertSame(22, $resolved->slot('student_id'));
        $this->assertSame(2, $resolved->slot('case_id'));
        $this->assertSame('Second', $resolved->slot('student_name'));
    }

    // ------------------------------------------------------------------- trace

    /**
     * Every stage exists from the moment the trace is created. This is what makes the
     * architecture legible: a reader sees all fifteen, not only the ones that fired.
     */
    public function test_the_trace_carries_every_stage_in_order(): void
    {
        $trace = new FlowTrace();
        $stages = $trace->toArray();

        $this->assertCount(15, $stages);

        $expected = [
            'conversation', 'gen_ai', 'agent', 'ontology', 'data', 'evidence', 'case',
            'explanation', 'template', 'recommendation', 'approval', 'workflow',
            'action', 'outcome', 'learning',
        ];

        $this->assertSame($expected, array_column($stages, 'key'));
        $this->assertSame(range(1, 15), array_column($stages, 'order'));

        foreach ($stages as $stage) {
            $this->assertSame(TraceStage::NOT_REACHED, $stage['status']);
            $this->assertNotSame('', $stage['component'], 'Every stage must name the class that implements it.');
            $this->assertNotSame('', $stage['surface'], 'Every stage must name where the user sees it.');
        }
    }

    /**
     * A stage that did not run must still say why. "Waiting on the approval above" is
     * the fact that explains the gate; an omitted row would read as a missing feature.
     */
    public function test_a_stage_that_did_not_run_reports_the_reason(): void
    {
        $trace = new FlowTrace();
        $trace->ran('agent', 'Ran.', [], ['table' => 'ai_agent_runs', 'ids' => [3]]);
        $trace->pending('approval', 'Waiting for a teacher.');
        $trace->notReached('workflow', 'Waiting on the human decision above.');

        $byKey = $this->byKey($trace->toArray());

        $this->assertSame(TraceStage::RAN, $byKey['agent']['status']);
        $this->assertSame([3], $byKey['agent']['records']['ids']);
        $this->assertSame(TraceStage::PENDING, $byKey['approval']['status']);
        $this->assertSame(TraceStage::NOT_REACHED, $byKey['workflow']['status']);
        $this->assertSame('Waiting on the human decision above.', $byKey['workflow']['note']);
    }

    /**
     * The ladder is the form a person reads when there is no UI in front of them, so
     * every stage must produce exactly one line.
     */
    public function test_the_ladder_has_one_line_per_stage(): void
    {
        $trace = new FlowTrace();
        $trace->ran('conversation', 'Accepted.');
        $trace->blocked('agent', 'Role not permitted.');

        $ladder = $trace->toLadder();

        $this->assertCount(15, $ladder);
        $this->assertStringContainsString('Conversational AI', $ladder[0]);
        $this->assertStringContainsString('Role not permitted.', $ladder[2]);
    }

    public function test_stage_counts_summarise_the_turn(): void
    {
        $trace = new FlowTrace();
        $trace->ran('conversation', 'a');
        $trace->ran('gen_ai', 'b');
        $trace->skipped('agent', 'c');

        $counts = $trace->summaryCounts();

        $this->assertSame(2, $counts[TraceStage::RAN]);
        $this->assertSame(1, $counts[TraceStage::SKIPPED]);
        $this->assertSame(12, $counts[TraceStage::NOT_REACHED]);
    }

    /**
     * The catalogue is what the console offers and what the test plan works through, so
     * every intent must declare a label and its required slots.
     */
    public function test_every_intent_declares_what_it_needs(): void
    {
        foreach ($this->classifier->catalogue() as $intent) {
            $this->assertNotSame('', $intent['label']);
            $this->assertNotSame('', $intent['description']);
            $this->assertIsArray($intent['requires']);
            $this->assertNotSame(Intent::UNKNOWN, $intent['key']);
        }
    }

    /**
     * Trace stages come back as an ordered list; these assertions want them by key.
     */
    private function byKey(array $stages): array
    {
        $keyed = [];

        foreach ($stages as $stage) {
            $keyed[$stage['key']] = $stage;
        }

        return $keyed;
    }
}
