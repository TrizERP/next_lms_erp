<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\AiTutorContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AI Tutor grounding and governance.
 *
 * The governance half is the point of these tests. Alpha School / TimeBack
 * disabled chat outright because "90% of kids use chatbots to cheat"; the
 * response taken here keeps the tutor but refuses to hand over an answer the
 * learner has not tried to reach. A rule like that written into a system
 * prompt is a request the model may ignore, so it is resolved server-side and
 * asserted here as data.
 */
class AiTutorContextTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $studentId;

    private int $conceptId;

    private int $nodeId;

    private AiTutorContextService $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tutor = app(AiTutorContextService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Tutor Test School',
            'ShortCode' => 'TT' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'tutor@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'tutor@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Tutor',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Tutor Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '9',
            'short_name' => '9',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Tutor Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Tutor Concept',
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'chapter_id' => $chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $this->nodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function context(): ?array
    {
        return $this->tutor->forConcept($this->studentId, $this->conceptId, $this->subInstituteId);
    }

    /** One logged attempt. `hintUsed` is what separates a genuine attempt from an assisted one. */
    private function logAttempt(bool $hintUsed = false, bool $correct = false): void
    {
        DB::table('eso_response_log')->insert([
            'student_id' => $this->studentId,
            'concept_id' => $this->conceptId,
            'node_id' => $this->nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'question_id' => random_int(100000, 999999),
            'correct' => $correct,
            'hint_used' => $hintUsed,
            'mode' => LearnerNodeState::MODE_INDEPENDENT,
            // eso_response_log is append-only and carries created_at alone.
            'created_at' => now(),
        ]);
    }

    public function test_a_learner_who_has_not_tried_yet_gets_socratic_questioning_only(): void
    {
        $governance = $this->context()['governance'];

        $this->assertSame('socratic_only', $governance['mode']);
        $this->assertSame(0, $governance['genuine_attempts']);
        $this->assertSame(2, $governance['attempts_remaining']);
    }

    public function test_direct_explanation_unlocks_only_after_the_required_genuine_attempts(): void
    {
        $this->logAttempt();
        $this->assertSame(
            'socratic_only',
            $this->context()['governance']['mode'],
            'One attempt is not the configured threshold. Unlocking early would let a single throwaway try buy the explanation.'
        );

        $this->logAttempt();
        $governance = $this->context()['governance'];

        $this->assertSame('direct_explanation_allowed', $governance['mode']);
        $this->assertSame(2, $governance['genuine_attempts']);
        $this->assertSame(0, $governance['attempts_remaining']);
    }

    public function test_an_attempt_the_learner_was_helped_through_does_not_count_as_genuine(): void
    {
        $this->logAttempt(hintUsed: true);
        $this->logAttempt(hintUsed: true);
        $this->logAttempt(hintUsed: true);

        $governance = $this->context()['governance'];

        $this->assertSame(0, $governance['genuine_attempts']);
        $this->assertSame(
            'socratic_only',
            $governance['mode'],
            'Being walked through a question with hints is not evidence of an attempt to reach the answer independently, which is the thing the rule asks for.'
        );
    }

    public function test_assessment_answers_are_never_unlocked_however_many_attempts_are_logged(): void
    {
        foreach (range(1, 25) as $ignored) {
            $this->logAttempt();
        }

        $governance = $this->context()['governance'];

        $this->assertSame('direct_explanation_allowed', $governance['mode']);
        $this->assertSame(
            'never',
            $governance['assessment_answers'],
            'Explanation and answers are separate clauses on purpose. If attempts unlocked both, two throwaway attempts would buy the answer key — the exact loophole the rule exists to close.'
        );
    }

    public function test_the_threshold_is_configurable_rather_than_hardcoded(): void
    {
        config()->set('pal_content.ai_tutor.min_genuine_attempts_for_direct_answer', 5);

        $this->logAttempt();
        $this->logAttempt();

        $governance = $this->context()['governance'];

        $this->assertSame('socratic_only', $governance['mode']);
        $this->assertSame(5, $governance['attempts_required']);
        $this->assertSame(3, $governance['attempts_remaining']);
    }

    public function test_only_the_misconception_eso_actually_flagged_is_surfaced(): void
    {
        $flagged = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'tutor_flagged_' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'Treats a negative sign as subtraction.',
            'error_pattern' => '-3 read as "minus 3" mid-expression',
            'corrective_action' => 'Contrast a signed value with an operation using the same symbol.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A second misconception on the same concept that this learner has NOT
        // demonstrated. A tutor handed both would guess which one applies.
        DB::table('pal_misconception_library')->insert([
            'tag' => 'tutor_unflagged_' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'Confuses integers with whole numbers.',
            'corrective_action' => 'Sort a mixed set into both categories.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $this->nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => 0.3,
                'attempts' => 2,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'active_misconception_id' => $flagged,
                'last_seen_at' => now(),
            ]
        );

        $misconceptions = $this->context()['misconceptions'];

        $this->assertCount(1, $misconceptions);
        $this->assertSame($flagged, $misconceptions[0]['id']);
        $this->assertSame(
            'Contrast a signed value with an operation using the same symbol.',
            $misconceptions[0]['corrective_action'],
            'The authored remedy must travel with the flag — a tutor that improvises a correction is not using the misconception library.'
        );
    }

    public function test_no_misconception_is_reported_when_none_is_active(): void
    {
        LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $this->nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => 0.8,
                'attempts' => 3,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'active_misconception_id' => null,
                'last_seen_at' => now(),
            ]
        );

        $this->assertSame([], $this->context()['misconceptions']);
    }

    public function test_a_concept_with_no_eso_nodes_yields_no_tutor_context_at_all(): void
    {
        $bareConceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Concept With No Nodes',
            'chapter_id' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $this->assertNull(
            $this->tutor->forConcept($this->studentId, $bareConceptId, $this->subInstituteId),
            'With nothing authored to ground on, the caller must be able to decline to open a session rather than invite the model to answer from its own knowledge.'
        );
    }

    public function test_the_governance_rules_are_stated_for_the_caller_not_left_implicit(): void
    {
        $rules = $this->context()['governance']['rules'];

        $this->assertNotEmpty($rules);

        $joined = strtolower(implode(' ', $rules));
        $this->assertStringContainsString('socratic', $joined);
        $this->assertStringContainsString('never reveal the answer', $joined);
        $this->assertStringContainsString('grounding', $joined);
    }
}
