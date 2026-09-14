<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\CapabilityConfidenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Capability Confidence.
 *
 * The spec (#25) is explicit that this must be evidence-driven — attempt
 * variety, hint dependence, independent versus assisted — and NOT just a raw
 * score. So the tests that matter most are the ones showing two learners with
 * the SAME mastery estimate landing in different bands because the evidence
 * behind them differs.
 */
class CapabilityConfidenceTest extends TestCase
{
    use DatabaseTransactions;

    private CapabilityConfidenceService $confidence;

    private int $subInstituteId;

    private int $conceptId;

    private int $nodeId;

    private int $questionCounter = 5500000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->confidence = app(CapabilityConfidenceService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Capability School',
            'ShortCode' => 'CC' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'cc@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'cc@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Capability Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '8',
            'short_name' => '8',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Capability Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Definition of Integers',
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

    private function learner(string $name): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => $name,
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);
    }

    private function setMastery(int $studentId, float $estimate): void
    {
        LearnerNodeState::updateOrCreate(
            ['student_id' => $studentId, 'node_id' => $this->nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $estimate,
                'attempts' => 6,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * @param  int|null  $questionId  reuse one id to model "the same question again"
     */
    private function attempt(int $studentId, bool $hintUsed, string $mode, ?int $questionId = null): void
    {
        DB::table('eso_response_log')->insert([
            'student_id' => $studentId,
            'concept_id' => $this->conceptId,
            'node_id' => $this->nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'question_id' => $questionId ?? $this->questionCounter++,
            'correct' => true,
            'hint_used' => $hintUsed,
            'mode' => $mode,
            'created_at' => now(),
        ]);
    }

    private function score(int $studentId): ?array
    {
        return $this->confidence->forConcept($studentId, $this->conceptId, $this->subInstituteId);
    }

    public function test_two_learners_with_the_same_mastery_score_can_have_different_capability(): void
    {
        // Independent, across five distinct questions.
        $strong = $this->learner('Strong');
        $this->setMastery($strong, 0.90);
        foreach (range(1, 5) as $i) {
            $this->attempt($strong, hintUsed: false, mode: LearnerNodeState::MODE_INDEPENDENT);
        }

        // Same estimate, reached with hints on one question repeated.
        $shallow = $this->learner('Shallow');
        $this->setMastery($shallow, 0.90);
        $repeated = $this->questionCounter++;
        foreach (range(1, 5) as $i) {
            $this->attempt($shallow, hintUsed: true, mode: LearnerNodeState::MODE_GUIDED, questionId: $repeated);
        }

        $strongScore = $this->score($strong);
        $shallowScore = $this->score($shallow);

        $this->assertSame(
            0.90,
            $strongScore['evidence']['mastery_estimate'],
            'Both learners must genuinely share a mastery estimate, or this test proves nothing.'
        );
        $this->assertSame(0.90, $shallowScore['evidence']['mastery_estimate']);

        $this->assertGreaterThan(
            $shallowScore['capability_confidence'],
            $strongScore['capability_confidence'],
            'A learner who answered five different questions unaided has demonstrated more than one who was walked through the same question five times, and the score has to say so. Otherwise this is just the mastery estimate with extra steps.'
        );
    }

    public function test_repeating_one_question_does_not_count_as_broad_evidence(): void
    {
        $student = $this->learner('Repeater');
        $this->setMastery($student, 0.95);

        $sameQuestion = $this->questionCounter++;
        foreach (range(1, 10) as $i) {
            $this->attempt($student, hintUsed: false, mode: LearnerNodeState::MODE_INDEPENDENT, questionId: $sameQuestion);
        }

        $result = $this->score($student);

        $this->assertSame(1, $result['evidence']['distinct_questions']);
        $this->assertSame(
            0.2,
            $result['evidence']['variety'],
            'Ten attempts at one item is one piece of evidence repeated, not ten.'
        );
    }

    public function test_a_hint_free_answer_inside_a_guided_session_is_not_independent(): void
    {
        $student = $this->learner('Scaffolded');
        $this->setMastery($student, 0.80);

        foreach (range(1, 4) as $i) {
            $this->attempt($student, hintUsed: false, mode: LearnerNodeState::MODE_GUIDED);
        }

        $this->assertSame(
            0.0,
            $this->score($student)['evidence']['independence'],
            'The session was scaffolded even where a hint was not taken; counting it as independent overstates what the learner did alone.'
        );
    }

    public function test_the_confirmed_bands_are_applied_and_configurable(): void
    {
        $student = $this->learner('Bander');
        $this->setMastery($student, 1.0);
        foreach (range(1, 5) as $i) {
            $this->attempt($student, hintUsed: false, mode: LearnerNodeState::MODE_INDEPENDENT);
        }

        // Perfect on every input.
        $this->assertSame(1.0, $this->score($student)['capability_confidence']);
        $this->assertSame('stable_mastery', $this->score($student)['band']);
        $this->assertSame('delayed_retrieval', $this->score($student)['action']);

        // Configurable, not hardcoded — the spec's explicit requirement.
        config()->set('pal_content.capability_confidence.bands', [
            ['key' => 'everything_is_relearn', 'min' => 0.0, 'action' => 'relearn'],
        ]);

        $this->assertSame('everything_is_relearn', $this->score($student)['band']);
    }

    public function test_a_learner_below_the_evidence_floor_has_no_confidence_rather_than_a_low_one(): void
    {
        $student = $this->learner('Barely Started');
        $this->setMastery($student, 0.30);
        $this->attempt($student, hintUsed: false, mode: LearnerNodeState::MODE_INDEPENDENT);

        $this->assertNull(
            $this->score($student),
            'A confidence built from one attempt is a guess wearing a number. ADR-001 §5: absence of evidence is a reason to gather more, not a low score.'
        );
    }

    public function test_capability_is_not_simply_the_mastery_estimate(): void
    {
        $student = $this->learner('Mixed');
        $this->setMastery($student, 1.0);

        // Fully mastered by BKT, but every attempt hinted and on one question.
        $sameQuestion = $this->questionCounter++;
        foreach (range(1, 4) as $i) {
            $this->attempt($student, hintUsed: true, mode: LearnerNodeState::MODE_GUIDED, questionId: $sameQuestion);
        }

        $result = $this->score($student);

        $this->assertLessThan(
            1.0,
            $result['capability_confidence'],
            'A perfect mastery estimate must not produce perfect capability when nothing about the evidence is independent or varied — that is exactly the "raw score" the spec rules out.'
        );
        // 1.0*0.60 + 0.0*0.25 + 0.2*0.15 = 0.63
        $this->assertSame(0.63, $result['capability_confidence']);
        $this->assertSame('consolidating', $result['band']);
    }

    public function test_misconfigured_weights_fail_loudly_rather_than_shifting_every_band(): void
    {
        $student = $this->learner('Victim');
        $this->setMastery($student, 0.9);
        foreach (range(1, 4) as $i) {
            $this->attempt($student, hintUsed: false, mode: LearnerNodeState::MODE_INDEPENDENT);
        }

        config()->set('pal_content.capability_confidence.weights', [
            'mastery' => 0.6,
            'independence' => 0.25,
            'variety' => 0.9, // sums to 1.75
        ]);

        $this->expectException(RuntimeException::class);

        $this->score($student);
    }

    public function test_a_concept_with_no_nodes_has_no_capability_score(): void
    {
        $student = $this->learner('Nowhere');

        $bareConceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'No Nodes',
            'chapter_id' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $this->assertNull($this->confidence->forConcept($student, $bareConceptId, $this->subInstituteId));
    }
}
