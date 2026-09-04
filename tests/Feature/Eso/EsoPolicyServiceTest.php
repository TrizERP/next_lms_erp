<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adaptive Learning Engine — Developer Brief v1, Phase 14.
 *
 * Exercises EsoPolicyService directly (not over HTTP) so these run fast and
 * pin down the D1-D5 rules precisely. Every fixture is real rows in the
 * configured DB, rolled back via DatabaseTransactions — same convention as
 * tests/Feature/Pal/PalMisconceptionDetectionTest.php.
 *
 * Every "attempt" fixture goes through makeAnswer() and is submitted as
 * answer_master_id — EsoPolicyService resolves correctness server-side from
 * answer_master.correct_answer, it never trusts a client-supplied flag, so
 * these tests exercise that exact path rather than a shortcut around it.
 */
class EsoPolicyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    private int $kNodeId;

    private int $aNodeId;

    private int $questionCounter = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Test School',
            'ShortCode' => 'ESO' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Eso',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Test Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '9',
            'short_name' => '9',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'ESO Test Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = $this->makeConcept('ESO Test Concept');
        [$this->kNodeId, $this->aNodeId] = $this->makeKANodes($this->conceptId);
    }

    private function makeConcept(string $name): int
    {
        return (int) DB::table('lms_concept')->insertGetId([
            'name' => $name,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    /** @return array{0:int,1:int} [kNodeId, aNodeId] */
    private function makeKANodes(int $conceptId): array
    {
        $k = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $a = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'A',
            'label' => 'Application node',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$k, $a];
    }

    private function setMastery(int $nodeId, float $mastery, string $status = LearnerNodeState::STATUS_LEARNING): LearnerNodeState
    {
        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $mastery,
                'attempts' => 1,
                'status' => $status,
                'last_seen_at' => now(),
            ]
        );
    }

    /** A real answer_master row so correctness is resolved server-side, exactly as production does. */
    private function makeAnswer(bool $correct, ?int $misconceptionId = null): int
    {
        return (int) DB::table('answer_master')->insertGetId([
            'question_id' => $this->questionCounter++,
            'answer' => $correct ? 'The correct option' : 'A wrong option',
            'correct_answer' => $correct ? 1 : 0,
            'misconception_id' => $misconceptionId,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);
    }

    // ── TEST 1: Known student → D1 skip ─────────────────────────────────

    public function test_a_student_who_diagnoses_above_the_skip_threshold_is_marked_mastered_and_skipped(): void
    {
        $rightAnswer = $this->makeAnswer(true);

        $results = $this->policy->scoreDiagnostic($this->studentId, $this->conceptId, $this->subInstituteId, [
            ['node_id' => $this->kNodeId, 'answer_master_id' => $rightAnswer],
            ['node_id' => $this->kNodeId, 'answer_master_id' => $rightAnswer], // +0.4 x2 diagnostic weight = 0.8, clamped, >= 0.80
        ]);

        $kResult = collect($results)->firstWhere('node_id', $this->kNodeId);
        $this->assertTrue($kResult['skip'], 'A node diagnosed at or above 0.80 must be skip-eligible.');

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_MASTERED, $state->status);

        $log = DecisionLog::forStudent($this->studentId)->where('node_id', $this->kNodeId)->latest()->first();
        $this->assertStringContainsString('D1', $log->rule_fired);
        $this->assertSame('skip_instruction', $log->action);
    }

    // ── TEST 2: Weak prerequisite → D2 remediation ──────────────────────

    public function test_a_weak_prerequisite_blocks_the_main_concept_with_d2_remediation(): void
    {
        $prereqConceptId = $this->makeConcept('Prerequisite Concept');
        [$prereqK, $prereqA] = $this->makeKANodes($prereqConceptId);

        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $this->conceptId,
            'to_concept_id' => $prereqConceptId,
            'relation_type' => 'requires',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Prerequisite mastery well below 0.75.
        $this->setMastery($prereqK, 0.4);
        $this->setMastery($prereqA, 0.4);

        // Main concept has already been diagnosed (so D1-entry doesn't fire first).
        $this->setMastery($this->kNodeId, 0.3);
        $this->setMastery($this->aNodeId, 0.3);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('remediate_prerequisite', $action['action']);
        $this->assertSame($prereqConceptId, $action['prerequisite_concept_id']);
        $this->assertSame('D2', $action['rule_fired']);

        $log = DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->latest()->first();
        $this->assertStringContainsString('D2', $log->rule_fired);
        $this->assertSame('remediate_prerequisite', $log->action);
    }

    // ── TEST 3: Misconception detected → D3 contrast pair + retest ──────

    public function test_a_distractor_mapped_to_a_misconception_serves_a_contrast_pair_and_clears_only_on_a_clean_retest(): void
    {
        $misconceptionId = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'eso_test_misconception_' . random_int(1000, 9999),
            'concept_ref_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'Confuses X with Y.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pal_misconception_corrective')->insert([
            'misconception_id' => $misconceptionId,
            'sub_institute_id' => 0,
            'title' => 'Why X is not Y',
            'body' => 'X happens when ...; Y happens when ... — here is the contrast.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wrongAnswerId = $this->makeAnswer(false, $misconceptionId);
        $stillWrongAnswerId = $this->makeAnswer(false);
        $rightAnswerId = $this->makeAnswer(true);

        $this->setMastery($this->kNodeId, 0.5);

        // Wrong answer via the misconception-mapped distractor -> D3 fires.
        $result = $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $wrongAnswerId,
        ]);

        $this->assertSame('serve_contrast_pair', $result['action']);
        $this->assertSame($misconceptionId, $result['misconception_id']);
        $this->assertNotNull($result['contrast_pair'], 'D3 must select a corrective, not just detect.');
        $this->assertNotEmpty($result['llm_instruction'], 'D3 must produce a constrained instruction for Pal.');

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED, $state->status);
        $this->assertSame($misconceptionId, $state->active_misconception_id);

        // A second wrong answer (not yet a clean retest) must not clear the flag.
        $stillWrong = $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $stillWrongAnswerId,
        ]);
        $this->assertSame('serve_contrast_pair', $stillWrong['action']);
        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED, $state->status);

        // A clean (correct) retest clears the flag.
        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $rightAnswerId,
        ]);
        $state->refresh();
        $this->assertNotSame(LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED, $state->status);
        $this->assertNull($state->active_misconception_id);

        $correctionLog = DecisionLog::forStudent($this->studentId)->where('node_id', $this->kNodeId)
            ->where('action', 'misconception_corrected')->first();
        $this->assertNotNull($correctionLog, 'Correction must only be logged after the clean retest.');
    }

    public function test_a_wrong_answer_with_no_misconception_mapping_is_a_generic_error_and_does_not_fire_d3(): void
    {
        $genericWrongAnswerId = $this->makeAnswer(false);

        $this->setMastery($this->kNodeId, 0.5);

        $result = $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $genericWrongAnswerId,
        ]);

        $this->assertNotSame('serve_contrast_pair', $result['action']);

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertNotSame(LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED, $state->status);
    }

    // ── TEST 4 / 5: mastery verdict ──────────────────────────────────────

    public function test_high_knowledge_but_low_application_mastery_is_not_mastered(): void
    {
        $this->setMastery($this->kNodeId, 0.9);
        $this->setMastery($this->aNodeId, 0.5); // below 0.70

        $verdict = $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertFalse($verdict['mastered']);
        $this->assertSame('continue_practice', $verdict['action']);
    }

    public function test_knowledge_and_application_above_threshold_with_no_misconception_is_mastered(): void
    {
        $this->setMastery($this->kNodeId, 0.85);
        $this->setMastery($this->aNodeId, 0.75);

        $verdict = $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertTrue($verdict['mastered']);
        $this->assertSame('mastered_stop_practice', $verdict['action']);

        $kState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_MASTERED, $kState->status);
        $this->assertNotNull($kState->next_review_at, 'D5 must be scheduled the moment D4 mastery is reached.');
    }

    public function test_an_active_misconception_blocks_mastery_even_with_high_scores(): void
    {
        $this->setMastery($this->kNodeId, 0.9);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED);

        $verdict = $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertFalse($verdict['mastered'], 'A critical misconception must block mastery regardless of accuracy scores.');
    }

    // ── TEST 6 / 7: D5 delayed retrieval ─────────────────────────────────

    public function test_mastery_schedules_a_delayed_retrieval_check_three_to_five_days_out(): void
    {
        $this->setMastery($this->kNodeId, 0.85);
        $this->setMastery($this->aNodeId, 0.75);

        $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $daysOut = now()->diffInDays($state->next_review_at, false);

        $this->assertGreaterThanOrEqual(3, $daysOut);
        $this->assertLessThanOrEqual(5, $daysOut);
    }

    public function test_a_failed_retrieval_check_reloops_only_the_failed_node(): void
    {
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $wrong = $this->makeAnswer(false);
        $right = $this->makeAnswer(true);

        // K fails its retrieval check, A is untouched.
        $result = $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $wrong],
            ['answer_master_id' => $right],
        ]);

        $this->assertSame('reloop_node', $result['action']);

        $kState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $aState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->aNodeId)->first();

        $this->assertSame(LearnerNodeState::STATUS_LEARNING, $kState->status, 'Only the failed node re-loops.');
        $this->assertSame(LearnerNodeState::STATUS_MASTERED, $aState->status, 'An unrelated mastered node must not be touched.');
    }

    public function test_next_action_surfaces_a_due_retrieval_check_instead_of_skipping_past_a_mastered_node(): void
    {
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)
            ->update(['next_review_at' => now()->subDay()]); // overdue
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED); // not due, no next_review_at set

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('retrieval_due', $action['action']);
        $this->assertSame($this->kNodeId, $action['node_id']);
        $this->assertSame('D5', $action['rule_fired']);

        $log = DecisionLog::forStudent($this->studentId)->where('node_id', $this->kNodeId)->latest()->first();
        $this->assertSame('D5: scheduled retrieval check is due', $log->rule_fired);
    }

    public function test_next_action_does_not_surface_a_mastered_node_with_no_review_due_yet(): void
    {
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)
            ->update(['next_review_at' => now()->addDays(3)]); // not due yet
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotSame('retrieval_due', $action['action']);
    }

    public function test_a_passed_retrieval_check_moves_the_node_from_mastered_to_retained(): void
    {
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $right1 = $this->makeAnswer(true);
        $right2 = $this->makeAnswer(true);

        $result = $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $right1],
            ['answer_master_id' => $right2],
        ]);

        $this->assertSame('retained', $result['action']);
        $this->assertSame(LearnerNodeState::STATUS_RETAINED, $result['status']);
    }

    // ── TEST 8: hint usage ────────────────────────────────────────────────

    public function test_hint_assisted_independent_practice_correctness_does_not_count_toward_mastery(): void
    {
        $state = $this->setMastery($this->kNodeId, 0.5);
        $state->practice_mode = LearnerNodeState::MODE_INDEPENDENT;
        $state->save();

        $before = $state->mastery_estimate;

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $this->makeAnswer(true),
            'hint_used' => true,
            'mode' => 'independent',
        ]);

        $state->refresh();
        $this->assertSame($before, $state->mastery_estimate, 'A hint-assisted independent-practice answer must not move the mastery estimate.');
        $this->assertSame(1, $state->hint_used_count);
        $this->assertSame(0, $state->consecutive_correct, 'A hint-assisted correct answer must not count toward the 2-consecutive-correct advance rule either.');

        // The same answer, hint-free, DOES count.
        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $this->makeAnswer(true),
            'hint_used' => false,
            'mode' => 'independent',
        ]);
        $state->refresh();
        $this->assertGreaterThan($before, $state->mastery_estimate);
    }

    public function test_hint_assisted_guided_practice_still_counts(): void
    {
        $state = $this->setMastery($this->kNodeId, 0.5);
        $before = $state->mastery_estimate;

        $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $this->makeAnswer(true),
            'hint_used' => true,
            'mode' => 'guided',
        ]);

        $state->refresh();
        $this->assertGreaterThan($before, $state->mastery_estimate, 'Guided practice counts even with a hint — only independent practice requires hint-free correctness.');
    }

    // ── TEST 9: decision log completeness ─────────────────────────────────

    public function test_every_d1_through_d5_decision_writes_an_audit_record(): void
    {
        // D1 (entry diagnostic prompt).
        $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertTrue(
            DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->where('rule_fired', 'like', 'D1%')->exists()
        );

        // D4 (mastery verdict, explicit call).
        $this->setMastery($this->kNodeId, 0.85);
        $this->setMastery($this->aNodeId, 0.75);
        $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertTrue(
            DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->where('rule_fired', 'like', 'D4%')->exists()
        );

        // D5 (retrieval check).
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $this->makeAnswer(true)],
        ]);
        $this->assertTrue(
            DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->where('rule_fired', 'like', 'D5%')->exists()
        );

        // Every logged row must be traceable to a deterministic rule, never "the AI decided".
        $rows = DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->get();
        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^D[1-5]/', $row->rule_fired, "rule_fired '{$row->rule_fired}' must name a D1-D5 rule.");
        }
    }

    // ── TEST 10: the engine decides, Pal only renders ─────────────────────

    public function test_the_resolver_never_calls_an_llm_and_the_instruction_it_produces_is_a_fixed_string(): void
    {
        // No Http::fake() is configured for this test. If nextAction()/
        // recordAttempt() ever made a real outbound LLM call, this test
        // would attempt real network I/O (and fail/hang in CI) rather than
        // pass silently — its passing is itself evidence the resolver makes
        // no such call.
        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('diagnostic', $action['action']);

        $this->setMastery($this->kNodeId, 0.3);
        $teach = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertIsString($teach['llm_instruction']);
        $this->assertNotEmpty($teach['llm_instruction'], 'The engine must hand Pal a concrete instruction, not delegate the choice.');

        // Calling the resolver again with unchanged state must produce the
        // identical instruction — proof the instruction is a deterministic
        // function of engine state, not something an LLM was asked to invent.
        $teachAgain = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame($teach['llm_instruction'], $teachAgain['llm_instruction']);
    }

    // ── TEST 11-14: a node saturated at its own D4 threshold must not keep
    //     blocking the loop and getting re-served practice — reported live
    //     against a real student as "mastery 100%, 16 attempts, still
    //     practicing" ──────────────────────────────────────────────────────

    // CASE A: mastery_estimate = 1.0 on K, but A is below its own threshold.
    // Expected: NOT mastered; practice continues, but routed to the sibling
    // that actually needs it, not re-served for the already-saturated node.
    public function test_case_a_saturated_node_is_not_reported_mastered_and_practice_routes_to_the_weaker_sibling(): void
    {
        $this->setMastery($this->kNodeId, 1.0); // never diagnosed as skip-eligible — status stays STATUS_LEARNING
        $this->setMastery($this->aNodeId, 0.5); // below APPLICATION_MASTERY_THRESHOLD (0.70)

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotSame('mastered_stop_practice', $action['action'], 'The concept is not D4-mastered — application accuracy is below 0.70.');
        $this->assertNotSame($this->kNodeId, $action['node_id'] ?? null, 'K is already at its own mastery cap; it must not keep being served practice.');
        $this->assertSame($this->aNodeId, $action['node_id'] ?? null, 'The weaker sibling must be what gets served next.');

        $kState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_LEARNING, $kState->status, 'mastery_estimate = 1.0 alone must never be reported as the D4 mastery verdict.');
    }

    // CASE B: knowledge >= 0.80, application >= 0.70, no active misconception.
    // Expected: D4 mastered_stop_practice, no practice question, next_review_at scheduled.
    public function test_case_b_every_node_individually_clearing_its_own_threshold_reaches_d4_mastery_without_a_diagnostic_skip(): void
    {
        $this->setMastery($this->kNodeId, 1.0); // >= 0.80, reached via ordinary practice, never diagnosed as skip
        $this->setMastery($this->aNodeId, 0.85); // >= 0.70

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('mastered_stop_practice', $action['action']);
        $this->assertArrayNotHasKey('node_id', $action, 'A mastery verdict is concept-level, not tied to a single node — no practice question is returned.');

        $kState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $aState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->aNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_MASTERED, $kState->status);
        $this->assertSame(LearnerNodeState::STATUS_MASTERED, $aState->status);
        $this->assertNotNull($kState->next_review_at, 'D5 must be scheduled the moment D4 mastery is reached, even without a diagnostic skip.');
        $this->assertNotNull($aState->next_review_at);
    }

    // CASE C: an already D4-mastered node/concept is requested again.
    // Expected: do NOT serve another practice question; return the mastered state.
    public function test_case_c_requesting_next_action_again_after_mastery_never_re_serves_practice(): void
    {
        $this->setMastery($this->kNodeId, 1.0, LearnerNodeState::STATUS_MASTERED);
        $this->setMastery($this->aNodeId, 0.85, LearnerNodeState::STATUS_MASTERED);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotSame('practice', $action['action']);
        $this->assertNotSame('teach', $action['action']);
        $this->assertSame('mastered_stop_practice', $action['action']);
    }

    // The exact reported symptom, through the real attempt-recording path
    // (not a directly-seeded fixture): A already qualifies; K is practiced
    // up to its own cap via real recordAttempt() calls. Before this fix,
    // recordAttempt()'s own evaluateProgress() fallback re-served K
    // indefinitely once it saturated, because it never re-checked whether a
    // sibling — or the whole concept — now qualified via the full resolver.
    public function test_recording_attempts_up_to_a_nodes_own_cap_reaches_mastery_instead_of_repeating_that_node(): void
    {
        $this->setMastery($this->aNodeId, 0.85); // already satisfies its own threshold

        $result = null;
        for ($i = 0; $i < 5; $i++) { // MASTERY_STEP 0.2 x 5 correct attempts = 1.0, clamped
            $result = $this->policy->recordAttempt($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
                'answer_master_id' => $this->makeAnswer(true),
            ]);
        }

        $kState = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertGreaterThanOrEqual(0.80, $kState->mastery_estimate);

        $this->assertSame(
            'mastered_stop_practice',
            $result['action'],
            'Once K reaches its own cap and A already qualifies, the very next resolved action must be the D4 verdict, not another practice question for K.'
        );

        // CASE C, exercised again through the real attempt path: state stays this way on a fresh call.
        $again = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('mastered_stop_practice', $again['action']);
    }
}
