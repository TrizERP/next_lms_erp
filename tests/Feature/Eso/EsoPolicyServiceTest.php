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

    /** An S (skill/transfer) node — the type masteryVerdict() deliberately does not gate on. */
    private function makeSNode(int $conceptId): int
    {
        return (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'S',
            'label' => 'Skill/transfer node',
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                // This helper means "a student already mid-practice on this
                // node", so it enters the teach → CFU → practice machine at
                // the practice phase — the same rule the CFU migration uses to
                // grandfather real in-flight learners (attempts > 0 has
                // demonstrably been taught and checked). Tests that exercise
                // the teach/CFU phases themselves seed state directly with
                // these markers left null.
                'taught_at' => now(),
                'cfu_passed_at' => now(),
            ]
        );
    }

    /**
     * A node the student has never been taught — the true entry state of the
     * teach → check-understanding → practice machine.
     */
    private function setUntaught(int $nodeId, float $mastery = 0.2): LearnerNodeState
    {
        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $mastery,
                // Set explicitly rather than left to the column default:
                // updateOrCreate does not re-read DB defaults, so these come
                // back null and make "CFU changed nothing" unassertable.
                'attempts' => 0,
                'consecutive_correct' => 0,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'last_seen_at' => now(),
                'taught_at' => null,
                'cfu_passed_at' => null,
                'cfu_attempts' => 0,
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

    // ── D2 staleness: a passing but OLD prerequisite gets a short probe ──

    /** A real servable MCQ (question + options + approved node tagging) so practiceItem() can actually return it. */
    private function makeServableQuestion(int $nodeId): int
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1, // MCQ — hydrateQuestion() serves these only
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'ESO staleness probe question',
            'points' => 1,
            'multiple_answer' => 0,
            'concept' => '',
            'subconcept' => '',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_by' => 1,
            'created_on' => now(),
            'answer' => '',
            'hint_text' => '',
        ]);

        foreach ([true, false] as $correct) {
            DB::table('answer_master')->insert([
                'question_id' => $questionId,
                'answer' => $correct ? 'Right' : 'Wrong',
                'correct_answer' => $correct ? 1 : 0,
                'sub_institute_id' => $this->subInstituteId,
                'created_on' => now(),
            ]);
        }

        DB::table('pal_question_metadata')->insert([
            'question_id' => $questionId,
            'node_id' => $nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved', // QuestionMetadata::servable()'s gate
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $questionId;
    }

    /** @return array{0:int,1:int,2:int} [prerequisiteConceptId, prerequisiteKNodeId, prerequisiteANodeId] */
    private function makePrerequisiteOfMainConcept(): array
    {
        $prereqConceptId = $this->makeConcept('Stale Prerequisite Concept');
        [$prereqK, $prereqA] = $this->makeKANodes($prereqConceptId);

        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $this->conceptId,
            'to_concept_id' => $prereqConceptId,
            'relation_type' => 'requires',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Main concept already diagnosed, so D1-entry doesn't fire ahead of D2.
        $this->setMastery($this->kNodeId, 0.3);
        $this->setMastery($this->aNodeId, 0.3);

        return [$prereqConceptId, $prereqK, $prereqA];
    }

    public function test_a_passing_but_stale_prerequisite_triggers_a_quick_probe_instead_of_full_remediation(): void
    {
        [$prereqConceptId, $prereqK, $prereqA] = $this->makePrerequisiteOfMainConcept();
        $this->makeServableQuestion($prereqK);

        // Both nodes comfortably above PREREQUISITE_THRESHOLD (the gate averages
        // across every node of the concept), and BOTH last practised long ago —
        // staleness reads the most recent evidence, so one fresh node would
        // legitimately make the prerequisite non-stale.
        $stale = now()->subDays(EsoPolicyService::PREREQUISITE_STALE_AFTER_DAYS + 5);
        foreach ([$prereqK, $prereqA] as $nodeId) {
            $state = $this->setMastery($nodeId, 0.95);
            $state->last_seen_at = $stale;
            $state->save();
        }

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('prerequisite_quick_probe', $action['action']);
        $this->assertSame($prereqConceptId, $action['prerequisite_concept_id']);
        $this->assertSame('D2', $action['rule_fired']);
        $this->assertNotEmpty($action['item']['options'] ?? [], 'The probe must carry a real answerable item.');

        $log = DecisionLog::forStudent($this->studentId)->forConcept($this->conceptId)->latest()->first();
        $this->assertSame('prerequisite_quick_probe', $log->action);
        $this->assertStringContainsString('D2', $log->rule_fired);
    }

    public function test_a_passing_and_fresh_prerequisite_is_not_probed(): void
    {
        [, $prereqK, $prereqA] = $this->makePrerequisiteOfMainConcept();
        $this->makeServableQuestion($prereqK);

        // Above threshold AND practised recently — nothing to re-establish.
        foreach ([$prereqK, $prereqA] as $nodeId) {
            $state = $this->setMastery($nodeId, 0.95);
            $state->last_seen_at = now()->subDays(2);
            $state->save();
        }

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotSame('prerequisite_quick_probe', $action['action']);
    }

    public function test_a_prerequisite_below_threshold_still_goes_to_full_remediation_even_when_stale(): void
    {
        [$prereqConceptId, $prereqK, $prereqA] = $this->makePrerequisiteOfMainConcept();
        $this->makeServableQuestion($prereqK);

        // Genuinely weak AND stale — weakness takes precedence over staleness.
        $stale = now()->subDays(EsoPolicyService::PREREQUISITE_STALE_AFTER_DAYS + 5);
        foreach ([$prereqK, $prereqA] as $nodeId) {
            $state = $this->setMastery($nodeId, 0.2);
            $state->last_seen_at = $stale;
            $state->save();
        }

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('remediate_prerequisite', $action['action']);
        $this->assertSame($prereqConceptId, $action['prerequisite_concept_id']);
    }

    // ── The "activation energy" practice-motivation message ─────────────

    public function test_the_practice_motivation_message_fires_once_at_the_first_practice_and_not_before_or_after(): void
    {
        // The resolver falls back to the concept's description when no
        // real-world application was extracted for it; without either, it
        // honestly produces nothing (asserted separately below).
        DB::table('lms_concept')->where('id', $this->conceptId)
            ->update(['description' => 'A worked description of the test concept.']);

        // Never taught → this node resolves to 'teach', not practice yet.
        $state = $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);

        $teach = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('teach', $teach['action']);
        $this->assertNull($teach['motivation_instruction'] ?? null, 'Nothing to activate yet — they have not understood it once.');

        // Taught and check-of-understanding passed, one attempt on file → the
        // first time this node resolves to practice.
        $state->refresh();
        $state->attempts = 1;
        $state->cfu_passed_at = now();
        $state->save();

        $firstPractice = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('practice', $firstPractice['action']);
        $this->assertIsString(
            $firstPractice['motivation_instruction'],
            'The concept has a description to fall back on, so a motivation instruction must be produced.'
        );
        $this->assertStringContainsString('practising', $firstPractice['motivation_instruction']);

        // attempts = 2 → ordinary practice, no repeat of the activation nudge.
        $state->refresh();
        $state->attempts = 2;
        $state->save();

        $laterPractice = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('practice', $laterPractice['action']);
        $this->assertNull($laterPractice['motivation_instruction'] ?? null, 'It is a one-time nudge, not a per-screen banner.');
    }

    public function test_no_motivation_message_is_invented_when_the_concept_has_no_relevance_data_at_all(): void
    {
        // No real-world application extracted AND no description — the honest
        // outcome is silence, not padded filler. (This is Concept 114's real
        // situation for the real-world slice, which is why it matters.)
        DB::table('lms_concept')->where('id', $this->conceptId)->update(['description' => null]);

        $state = $this->setMastery($this->kNodeId, 0.2);
        $state->attempts = 1;
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('practice', $action['action']);
        $this->assertNull($action['motivation_instruction'] ?? null);
    }

    // ── S-type nodes must not block the concept from reaching D4 ────────

    /**
     * Reported from manual QA: an S node showing "still practicing this node
     * (mastery 100%, 8 attempts)" and serving practice forever.
     *
     * masteryVerdict() judges a concept on its K and A nodes only and never
     * gates on S accuracy — but nextAction()'s per-node loop returned
     * teach/practice for any node that wasn't `status = mastered`, and only
     * masteryVerdict() ever sets that status. So a fully-practised S node
     * blocked the loop from ever reaching the verdict that would have
     * mastered it: a deadlock, and the concept could never enter retention.
     */
    public function test_a_fully_practised_s_node_does_not_block_the_concept_from_reaching_d4_mastery(): void
    {
        $sNodeId = $this->makeSNode($this->conceptId);

        // K and A both clear their own D4 thresholds...
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.9);
        // ...and S has been practised to the ceiling, but its status was never
        // flipped (only the verdict does that) — the exact reported state.
        $sState = $this->setMastery($sNodeId, 1.0);
        $sState->attempts = 8;
        $sState->save();

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotSame(
            'practice',
            $action['action'],
            'A maxed-out S node must not keep being served practice — it is not a D4 gate.'
        );
        $this->assertSame('mastered_stop_practice', $action['action']);

        // And the whole concept — S included — enters the retention ladder.
        foreach ([$this->kNodeId, $this->aNodeId, $sNodeId] as $nodeId) {
            $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $nodeId)->first();
            $this->assertSame(LearnerNodeState::STATUS_MASTERED, $state->status, "node {$nodeId} should be mastered");
            $this->assertNotNull($state->next_review_at, "node {$nodeId} should be scheduled for retrieval");
            $this->assertSame(0, (int) $state->retention_stage, "node {$nodeId} should enter the ladder at stage 0");
            $this->assertSame(
                now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[0])->toDateString(),
                $state->next_review_at->toDateString(),
                "node {$nodeId} should be scheduled ~2 days out (first rung)"
            );
        }
    }

    /** A never-attempted S node must still be taught — the fix must not skip the transfer task entirely. */
    public function test_an_unattempted_s_node_is_still_taught_before_the_concept_can_be_mastered(): void
    {
        $sNodeId = $this->makeSNode($this->conceptId);

        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.9);
        $this->setUntaught($sNodeId, 0.0);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('teach', $action['action'], 'The student must still meet the transfer task at least once.');
        $this->assertSame($sNodeId, $action['node_id']);
    }

    // ── STEP 6: Check For Understanding — the gate between teaching a node
    //     and starting scored practice on it ───────────────────────────────

    /**
     * A servable MCQ on a node whose two options are returned individually, so
     * a CFU can be answered correctly or incorrectly on demand. Same column
     * shape as makeServableQuestion() above — that helper discards the option
     * ids, which these tests need.
     *
     * @return array{0:int,1:int,2:int} [questionId, correctAnswerId, wrongAnswerId]
     */
    private function makeCfuQuestion(int $nodeId, ?int $misconceptionId = null): array
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1, // MCQ — hydrateQuestion() serves these only
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'A check-of-understanding question',
            'points' => 1,
            'multiple_answer' => 0,
            'concept' => '',
            'subconcept' => '',
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_by' => 1,
            'created_on' => now(),
            'answer' => '',
            'hint_text' => '',
        ]);

        $correct = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $questionId,
            'answer' => 'The correct option',
            'correct_answer' => 1,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);

        $wrong = (int) DB::table('answer_master')->insertGetId([
            'question_id' => $questionId,
            'answer' => 'A wrong option',
            'correct_answer' => 0,
            'misconception_id' => $misconceptionId,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);

        DB::table('pal_question_metadata')->insert([
            'question_id' => $questionId,
            'node_id' => $nodeId,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved', // QuestionMetadata::servable()'s gate
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$questionId, $correct, $wrong];
    }

    public function test_teaching_a_node_serves_no_scored_question_and_is_no_longer_practice_attempt_one(): void
    {
        $state = $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('teach', $action['action']);
        $this->assertSame('acknowledge', $action['expects'], 'The teach screen must not carry a scored question.');

        // Teaching stamps the phase marker but touches no mastery evidence.
        $state->refresh();
        $this->assertNotNull($state->taught_at);
        $this->assertSame(0, $state->attempts, 'Being taught is not an attempt.');
        $this->assertSame(0.2, round($state->mastery_estimate, 2), 'Being taught must not move mastery.');
    }

    public function test_a_taught_node_routes_to_check_understanding_before_any_practice(): void
    {
        $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);

        // First resolve teaches...
        $this->assertSame('teach', $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId)['action']);

        // ...the next one must be the CFU gate, NOT practice.
        $cfu = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('check_understanding', $cfu['action']);
        $this->assertSame('D1-CFU', $cfu['rule_fired']);
        $this->assertSame($this->kNodeId, $cfu['node_id']);
        $this->assertSame(EsoPolicyService::CFU_ITEM_COUNT, $cfu['cfu_item_count']);
    }

    public function test_a_silent_resolve_never_advances_the_student_past_the_teach_phase(): void
    {
        $state = $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);

        // Dashboards resolve silently purely to display a next step.
        $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId, silent: true);

        $state->refresh();
        $this->assertNull($state->taught_at, 'A silent (display-only) resolve must not stamp the teach phase.');
    }

    public function test_passing_the_check_of_understanding_releases_the_node_to_practice_without_counting_as_mastery_evidence(): void
    {
        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId, 0.4);
        $state->taught_at = now();
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        $before = ['mastery' => $state->mastery_estimate, 'attempts' => $state->attempts, 'streak' => $state->consecutive_correct];

        $result = $this->policy->recordCheckUnderstanding(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            [['answer_master_id' => $correctId], ['answer_master_id' => $correctId]]
        );

        $state->refresh();
        $this->assertNotNull($state->cfu_passed_at, 'A fully-correct check must release the node.');

        // The whole point of the gate: it is not mastery evidence.
        $this->assertSame($before['mastery'], $state->mastery_estimate, 'CFU must not move mastery_estimate.');
        $this->assertSame($before['attempts'], $state->attempts, 'CFU must not count as a practice attempt.');
        $this->assertSame($before['streak'], $state->consecutive_correct, 'CFU must not move the correct-streak.');

        $this->assertSame('practice', $result['action'], 'Understood → proceed to practice.');

        // ...but it is auditable, and distinguishable from practice.
        $this->assertSame(2, DB::table('eso_response_log')
            ->where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->where('mode', 'cfu')->count());
        $this->assertDatabaseHas('eso_decision_log', [
            'student_id' => $this->studentId,
            'node_id' => $this->kNodeId,
            'action' => 'understood',
        ]);
    }

    public function test_failing_the_check_of_understanding_routes_to_a_reteach_not_to_practice(): void
    {
        [, $correctId, $wrongId] = $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId, 0.4);
        $state->taught_at = now();
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        $result = $this->policy->recordCheckUnderstanding(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            [['answer_master_id' => $correctId], ['answer_master_id' => $wrongId]]
        );

        $state->refresh();
        $this->assertNull($state->cfu_passed_at, 'One wrong answer means the check was not passed.');
        $this->assertSame(1, $state->cfu_attempts);
        $this->assertSame(0.4, round($state->mastery_estimate, 2), 'Failing a CFU must not penalise mastery either.');

        $this->assertSame('reteach', $result['action'], 'Not understood → targeted re-explanation, not practice.');
        $this->assertDatabaseHas('eso_decision_log', [
            'student_id' => $this->studentId,
            'node_id' => $this->kNodeId,
            'action' => 'not_understood',
        ]);
    }

    public function test_a_wrong_check_answer_mapped_to_a_misconception_still_fires_d3_unchanged(): void
    {
        $misconceptionId = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'eso_cfu_misconception_' . random_int(1000, 9999),
            'concept_ref_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'A known mix-up',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, , $wrongId] = $this->makeCfuQuestion($this->kNodeId, $misconceptionId);

        $state = $this->setUntaught($this->kNodeId, 0.4);
        $state->taught_at = now();
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        $result = $this->policy->recordCheckUnderstanding(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            [['answer_master_id' => $wrongId]]
        );

        // A named misconception is a more specific diagnosis than "didn't
        // understand", so it must win the routing — losing the D3 signal just
        // because the wrong answer happened during a check would be a
        // regression on the old flow.
        $this->assertSame('serve_contrast_pair', $result['action']);

        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED, $state->status);
        $this->assertSame($misconceptionId, (int) $state->active_misconception_id);
    }

    public function test_a_student_who_cannot_pass_the_check_is_released_to_guided_practice_rather_than_looping_forever(): void
    {
        [, , $wrongId] = $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId, 0.4);
        $state->taught_at = now();
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        for ($cycle = 0; $cycle < EsoPolicyService::CFU_MAX_CYCLES; $cycle++) {
            $result = $this->policy->recordCheckUnderstanding(
                $this->studentId,
                $this->kNodeId,
                $this->conceptId,
                $this->subInstituteId,
                [['answer_master_id' => $wrongId]]
            );
        }

        $state->refresh();
        $this->assertNull($state->cfu_passed_at, 'The check genuinely was never passed.');
        $this->assertSame(EsoPolicyService::CFU_MAX_CYCLES, $state->cfu_attempts);
        $this->assertSame(
            'practice',
            $result['action'],
            'The loop guard must let a struggling student into ordinary practice rather than trapping them.'
        );
        $this->assertSame(
            LearnerNodeState::MODE_GUIDED,
            $state->practice_mode,
            'And it must be GUIDED practice — the CFU was not passed, nothing earned independent mode.'
        );
    }

    public function test_the_check_of_understanding_does_not_change_any_d1_to_d5_threshold(): void
    {
        // A guard against the CFU work quietly drifting the policy: these are
        // the constants the audit and the pilot are calibrated against.
        $this->assertSame(0.80, EsoPolicyService::SKIP_THRESHOLD);
        $this->assertSame(0.75, EsoPolicyService::PREREQUISITE_THRESHOLD);
        $this->assertSame(0.80, EsoPolicyService::KNOWLEDGE_MASTERY_THRESHOLD);
        $this->assertSame(0.70, EsoPolicyService::APPLICATION_MASTERY_THRESHOLD);
        $this->assertSame(2, EsoPolicyService::CONSECUTIVE_CORRECT_TO_ADVANCE);
        $this->assertSame([2, 7, 30, 60, 180], EsoPolicyService::RETENTION_LADDER_DAYS);
    }

    // ── STEP 5: connecting ESO to the existing PAL content model ─────────

    /**
     * A chapter extraction containing one concept, so the content model has
     * something real to project variants from. Mirrors the shape
     * SemanticSourceRepository reads (per-concept slice columns, not the blob).
     */
    private function seedExtraction(string $conceptName, array $conceptObject): int
    {
        return (int) DB::table('semantic_intelligence')->insertGetId([
            'chapter_id' => $this->chapterId,
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            // The chapter's NAME is not stored here — the projection joins it
            // from chapter_master (config('pal_content_model.source.chapter_join')).
            // The blob column is the misspelt one the extractor actually ships.
            'total_concepts' => 1,
            'full_intelegance_json' => json_encode([
                'chapter' => ['chapter_name' => 'ESO Test Chapter'],
                'concepts' => [$conceptObject + ['concept_name' => $conceptName]],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_teaching_falls_back_to_plain_text_when_the_content_model_has_nothing_for_the_concept(): void
    {
        // No semantic_intelligence row for this chapter at all — by far the
        // most common real state, and Concept 114's own state in the pilot
        // chapter (its extraction has 13 concepts and does not include it).
        $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('teach', $action['action']);
        $this->assertNull($action['learning_content'], 'No content must mean no content — never a fabricated variant.');
        $this->assertIsString($action['llm_instruction']);
        $this->assertStringContainsString('Teach K: Knowledge node', $action['llm_instruction'], 'Plain-text teaching must be unchanged.');
    }

    public function test_an_approved_authored_override_is_served_with_its_real_media_and_outranks_the_derived_variant(): void
    {
        $resolver = app(\App\Services\Eso\EsoLearningContentResolver::class);
        $projector = app(\App\Services\PAL\ContentModel\ContentModelProjector::class);
        $repo = app(\App\Services\PAL\ContentModel\SemanticSourceRepository::class);

        $conceptName = (string) DB::table('lms_concept')->where('id', $this->conceptId)->value('name');
        $semanticId = $this->seedExtraction($conceptName, [
            'concept' => ['definition' => 'A definition the extractor captured.'],
        ]);

        // The authoring table keys on exactly the node_key the projector emits.
        $nodeKey = $projector->nodeKey('concept', $semanticId, $repo->slug($conceptName), 'V1');

        DB::table('pal_cm_node_overrides')->insert([
            'node_key' => $nodeKey,
            'sub_institute_id' => $this->subInstituteId,
            'semantic_id' => $semanticId,
            'chapter_id' => $this->chapterId,
            'content_type' => 'concept',
            'title' => 'An authored walkthrough',
            'body' => 'The authored explanation.',
            'media_url' => 'https://cdn.example.test/metals.mp4',
            'quality_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $node = \App\Models\PAL\ConceptNode::find($this->kNodeId);
        $content = $resolver->forNode($node, $this->setUntaught($this->kNodeId), $this->subInstituteId);

        $this->assertNotNull($content, 'An approved authored asset must be served.');
        $this->assertSame('authored', $content['source']);
        $this->assertSame('https://cdn.example.test/metals.mp4', $content['media_url']);
        $this->assertSame('The authored explanation.', $content['body'], 'The authored body must win over the derived one.');
    }

    public function test_an_unapproved_override_is_never_served_to_a_student(): void
    {
        $resolver = app(\App\Services\Eso\EsoLearningContentResolver::class);
        $projector = app(\App\Services\PAL\ContentModel\ContentModelProjector::class);
        $repo = app(\App\Services\PAL\ContentModel\SemanticSourceRepository::class);

        $conceptName = (string) DB::table('lms_concept')->where('id', $this->conceptId)->value('name');
        // Nothing extracted for the concept either, so the ONLY candidate is
        // the draft override — if it leaked, this would be non-null.
        $semanticId = $this->seedExtraction('Some Other Concept', ['concept' => ['definition' => 'Unrelated.']]);

        DB::table('pal_cm_node_overrides')->insert([
            'node_key' => $projector->nodeKey('concept', $semanticId, $repo->slug($conceptName), 'V1'),
            'sub_institute_id' => $this->subInstituteId,
            'semantic_id' => $semanticId,
            'content_type' => 'concept',
            'body' => 'Unreviewed draft content.',
            'media_url' => 'https://cdn.example.test/draft.mp4',
            'quality_status' => 'draft', // NOT approved
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $node = \App\Models\PAL\ConceptNode::find($this->kNodeId);
        $content = $resolver->forNode($node, $this->setUntaught($this->kNodeId), $this->subInstituteId);

        $this->assertNull($content, 'Unreviewed content must never reach a student.');
    }

    public function test_a_derived_variant_backed_by_extracted_material_is_served_as_text_with_no_invented_media(): void
    {
        $resolver = app(\App\Services\Eso\EsoLearningContentResolver::class);

        $conceptName = (string) DB::table('lms_concept')->where('id', $this->conceptId)->value('name');
        $this->seedExtraction($conceptName, [
            'concept' => ['definition' => 'Metals conduct heat and electricity.'],
        ]);

        $node = \App\Models\PAL\ConceptNode::find($this->kNodeId);
        $content = $resolver->forNode($node, $this->setUntaught($this->kNodeId), $this->subInstituteId);

        $this->assertNotNull($content);
        $this->assertSame('derived', $content['source']);
        $this->assertStringContainsString('Metals conduct heat', (string) $content['body']);
        $this->assertNull(
            $content['media_url'],
            'A derived variant is an authoring SPECIFICATION, not an asset — it must never claim to have media.'
        );
    }

    public function test_a_failed_check_of_understanding_steps_the_content_reroute_ladder_to_a_different_format(): void
    {
        $resolver = app(\App\Services\Eso\EsoLearningContentResolver::class);

        $conceptName = (string) DB::table('lms_concept')->where('id', $this->conceptId)->value('name');
        // Material behind BOTH slot 1 (definition) and slot 3 (real-world
        // applications), so the ladder has somewhere different to go.
        $this->seedExtraction($conceptName, [
            'concept' => ['definition' => 'Metals conduct heat and electricity.'],
            'real_world_applications' => [
                ['application_type' => 'Everyday', 'example' => 'Copper wiring in a house.', 'relevance' => 'high'],
            ],
        ]);

        $node = \App\Models\PAL\ConceptNode::find($this->kNodeId);

        $first = $this->setUntaught($this->kNodeId);
        $firstContent = $resolver->forNode($node, $first, $this->subInstituteId);

        $retry = $this->setUntaught($this->kNodeId);
        $retry->cfu_attempts = 1;
        $retry->save();
        $retryContent = $resolver->forNode($node, $retry, $this->subInstituteId);

        $this->assertNotNull($firstContent);
        $this->assertNotNull($retryContent);
        $this->assertNotSame(
            $firstContent['variant'],
            $retryContent['variant'],
            'A re-explanation must move down the blueprint ladder, not re-serve the format that just failed.'
        );
    }

    public function test_connecting_the_content_model_does_not_disturb_the_misconception_corrective_media_path(): void
    {
        // The D3 corrective path resolves through MisconceptionLibraryService,
        // an entirely separate route from the learning-content resolver. This
        // pins that it still carries its own media.
        $misconceptionId = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'eso_content_misconception_' . random_int(1000, 9999),
            'concept_ref_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'Confuses conduction with reactivity.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pal_misconception_corrective')->insert([
            'misconception_id' => $misconceptionId,
            'sub_institute_id' => 0,
            'title' => 'Conduction is not reactivity',
            'body' => 'Here is the contrast.',
            'media_url' => 'https://cdn.example.test/corrective.mp4',
            'format' => 'video',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, , $wrongId] = $this->makeCfuQuestion($this->kNodeId, $misconceptionId);

        $this->setMastery($this->kNodeId, 0.4);
        $this->setMastery($this->aNodeId, 0.2);

        $result = $this->policy->recordAttempt(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            ['answer_master_id' => $wrongId]
        );

        $this->assertSame('serve_contrast_pair', $result['action']);
        $this->assertSame('https://cdn.example.test/corrective.mp4', $result['contrast_pair']['media_url'] ?? null);
    }

    // ── STEP 12: ESO outcomes reach the EXISTING evidence architecture ───

    public function test_a_practice_attempt_reaches_the_shared_evidence_ledger_and_concept_mastery(): void
    {
        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);

        $this->setMastery($this->kNodeId, 0.4);
        $this->setMastery($this->aNodeId, 0.2);

        $this->policy->recordAttempt(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            ['answer_master_id' => $correctId]
        );

        // The append-only ledger the coherence map and every replay reads.
        $this->assertDatabaseHas('pal_learning_evidence', [
            'learner_id' => $this->studentId,
            'concept_id' => $this->conceptId,
            'evidence_type' => 'question_response',
            // Explicitly identifiable as this engine, not the coherence map.
            'evidence_source' => \App\Services\Eso\EsoEvidenceBridge::SOURCE,
        ]);

        // ...and through MasteryUpdater's BKT replay into the shared mastery
        // row the Neo4j projection reads.
        $mastery = DB::table('pal_concept_mastery')
            ->where('learner_id', $this->studentId)
            ->where('concept_ref_id', $this->conceptId)
            ->first();

        $this->assertNotNull($mastery, 'ESO evidence must reach pal_concept_mastery, not stop at its own tables.');
        $this->assertSame(1, (int) $mastery->attempts);
        $this->assertSame(1, (int) $mastery->correct);
        $this->assertNull(
            $mastery->graph_synced_at,
            'The row must be left flagged as owed to the graph — that NULL IS the outbox the sweeper reads.'
        );
    }

    public function test_a_whole_diagnostic_is_published_as_one_operation_not_one_per_answer(): void
    {
        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);
        [, $correctId2] = $this->makeCfuQuestion($this->aNodeId);

        $this->policy->scoreDiagnostic($this->studentId, $this->conceptId, $this->subInstituteId, [
            ['node_id' => $this->kNodeId, 'answer_master_id' => $correctId],
            ['node_id' => $this->kNodeId, 'answer_master_id' => $correctId],
            ['node_id' => $this->aNodeId, 'answer_master_id' => $correctId2],
        ]);

        // Every response is recorded...
        $this->assertSame(3, DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('concept_id', $this->conceptId)
            ->where('evidence_type', 'question_response')
            ->count());

        // ...but the concept was replayed and projected once, as one operation.
        $mastery = DB::table('pal_concept_mastery')
            ->where('learner_id', $this->studentId)
            ->where('concept_ref_id', $this->conceptId)
            ->first();
        $this->assertNotNull($mastery);
        $this->assertSame(3, (int) $mastery->attempts, 'The BKT replay must see all three responses.');
    }

    public function test_check_of_understanding_responses_never_reach_the_shared_evidence_ledger(): void
    {
        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId, 0.4);
        $state->taught_at = now();
        $state->save();
        $this->setMastery($this->aNodeId, 0.2);

        $this->policy->recordCheckUnderstanding(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            [['answer_master_id' => $correctId], ['answer_master_id' => $correctId]]
        );

        // The engine promises a CFU "doesn't count towards your mastery". That
        // promise has to hold all the way out to the shared ledger and the
        // graph, not just inside learner_node_state.
        $this->assertSame(0, DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('concept_id', $this->conceptId)
            ->count());
        $this->assertDatabaseMissing('pal_concept_mastery', [
            'learner_id' => $this->studentId,
            'concept_ref_id' => $this->conceptId,
        ]);
    }

    public function test_a_mastery_verdict_is_recorded_as_an_outcome_that_does_not_inflate_the_bkt_estimate(): void
    {
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.85);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertSame('mastered_stop_practice', $action['action']);

        $this->assertDatabaseHas('pal_learning_evidence', [
            'learner_id' => $this->studentId,
            'concept_id' => $this->conceptId,
            'evidence_type' => \App\Services\Eso\EsoEvidenceBridge::OUTCOME_MASTERED,
            'evidence_source' => \App\Services\Eso\EsoEvidenceBridge::SOURCE,
        ]);

        // The crucial part: an outcome is not a 'question_response', so
        // MasteryUpdater::replay() never feeds it to BKT. A verdict must not
        // silently count as an extra correct answer.
        $this->assertSame(0, DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('concept_id', $this->conceptId)
            ->where('evidence_type', 'question_response')
            ->count());
    }

    public function test_a_detected_misconception_is_recorded_in_the_shared_ledger(): void
    {
        $misconceptionId = (int) DB::table('pal_misconception_library')->insertGetId([
            'tag' => 'eso_evidence_misconception_' . random_int(1000, 9999),
            'concept_ref_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'description' => 'A recorded mix-up.',
            'quality_status' => 'approved',
            'priority_level' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [, , $wrongId] = $this->makeCfuQuestion($this->kNodeId, $misconceptionId);

        $this->setMastery($this->kNodeId, 0.4);
        $this->setMastery($this->aNodeId, 0.2);

        $this->policy->recordAttempt(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            ['answer_master_id' => $wrongId]
        );

        $this->assertDatabaseHas('pal_learning_evidence', [
            'learner_id' => $this->studentId,
            'concept_id' => $this->conceptId,
            'evidence_type' => \App\Services\Eso\EsoEvidenceBridge::OUTCOME_MISCONCEPTION_DETECTED,
            'evidence_source' => \App\Services\Eso\EsoEvidenceBridge::SOURCE,
        ]);
    }

    public function test_evidence_never_leaks_across_students(): void
    {
        $otherStudentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Other',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);

        $this->setMastery($this->kNodeId, 0.4);
        $this->setMastery($this->aNodeId, 0.2);

        $this->policy->recordAttempt(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            ['answer_master_id' => $correctId]
        );

        // Everything written is scoped to the acting learner, and the other
        // student — same tenant, same concept, same node — gains nothing.
        $this->assertSame(0, DB::table('pal_learning_evidence')->where('learner_id', $otherStudentId)->count());
        $this->assertSame(0, DB::table('pal_concept_mastery')->where('learner_id', $otherStudentId)->count());
        $this->assertSame(1, DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('evidence_type', 'question_response')
            ->count());
    }

    public function test_a_failure_publishing_evidence_never_breaks_the_students_answer(): void
    {
        [, $correctId] = $this->makeCfuQuestion($this->kNodeId);

        $this->setMastery($this->kNodeId, 0.4);
        $this->setMastery($this->aNodeId, 0.2);

        // Downstream reporting is not allowed to fail a learning step — the
        // same rule the gamification hand-off already follows.
        $this->mock(\App\Services\PAL\Coherence\MasteryUpdater::class, function ($mock) {
            $mock->shouldReceive('recordBatch')->andThrow(new \RuntimeException('graph is down'));
            $mock->shouldReceive('recordOutcome')->andThrow(new \RuntimeException('graph is down'));
        });

        $policy = app(EsoPolicyService::class);
        $result = $policy->recordAttempt(
            $this->studentId,
            $this->kNodeId,
            $this->conceptId,
            $this->subInstituteId,
            ['answer_master_id' => $correctId]
        );

        $this->assertArrayHasKey('action', $result, 'The attempt must still resolve a next action.');
        $this->assertSame(1, DB::table('eso_response_log')
            ->where('student_id', $this->studentId)
            ->where('node_id', $this->kNodeId)
            ->count(), 'And the response must still be durable in the engine\'s own log.');
    }

    // ── STEP 10: the retention recap, wired into the due check ───────────

    /** A node whose scheduled spaced-review check has come due. */
    private function setRetrievalDue(int $nodeId, int $daysAgo = 7): LearnerNodeState
    {
        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => 0.9,
                'attempts' => 4,
                'consecutive_correct' => 2,
                'status' => LearnerNodeState::STATUS_MASTERED,
                'retention_stage' => 1,
                'last_seen_at' => now()->subDays($daysAgo),
                'next_review_at' => now()->subMinutes(5),
                'taught_at' => now()->subDays($daysAgo),
                'cfu_passed_at' => now()->subDays($daysAgo),
            ]
        );
    }

    public function test_a_due_retention_check_now_carries_a_recap_instead_of_returning_null(): void
    {
        // Real material for the concept to build a recap from.
        DB::table('lms_concept')->where('id', $this->conceptId)
            ->update(['description' => 'Metals conduct heat and electricity and can be hammered into sheets.']);

        $this->setRetrievalDue($this->kNodeId, daysAgo: 7);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('retrieval_due', $action['action']);
        $this->assertSame('D5', $action['rule_fired']);

        // The regression this test exists for: retentionSummaryInstruction()
        // was defined and never called, and this branch returned a hard-coded
        // llm_instruction of null, so the recap could never reach a student.
        $this->assertIsString($action['llm_instruction'], 'The due check must carry a recap, not null.');
        $this->assertStringContainsString('Metals conduct heat', $action['llm_instruction']);
        $this->assertStringContainsString('spaced-review check', $action['llm_instruction']);
        $this->assertStringContainsString('do not reveal or hint at the review questions', strtolower($action['llm_instruction']));

        // And a student-facing version for when Pal cannot render, so the
        // engine-facing instruction is never shown verbatim.
        $this->assertIsString($action['recap_fallback']);
        $this->assertStringNotContainsString('The student mastered', $action['recap_fallback']);

        $this->assertSame(7, $action['days_since_last_evidence']);
    }

    public function test_no_recap_is_invented_when_the_concept_has_no_material_behind_it(): void
    {
        // No description, no extraction, no authored content — an invented
        // "refresher" before a memory test would corrupt what D5 measures.
        DB::table('lms_concept')->where('id', $this->conceptId)->update(['description' => null]);

        $this->setRetrievalDue($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('retrieval_due', $action['action']);
        $this->assertNull($action['llm_instruction'], 'Silence beats a confident summary of something nobody wrote.');
        $this->assertNull($action['recap_fallback']);
    }

    public function test_the_recap_does_not_change_the_retention_ladder_or_reset_on_failure(): void
    {
        DB::table('lms_concept')->where('id', $this->conceptId)
            ->update(['description' => 'Metals conduct heat and electricity.']);

        [, $correctId, $wrongId] = $this->makeCfuQuestion($this->kNodeId);

        // Pass: stage 1 -> 2, scheduled at the THIRD rung (30 days).
        $state = $this->setRetrievalDue($this->kNodeId);
        $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId); // serves the recap
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $correctId],
        ]);

        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_RETAINED, $state->status);
        $this->assertSame(2, (int) $state->retention_stage);
        $this->assertSame(
            now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[2])->toDateString(),
            $state->next_review_at->toDateString(),
            'The ladder must be untouched by this work: [2, 7, 30, 60, 180].'
        );

        // Fail: the ladder resets to the first rung, exactly as before.
        $state = $this->setRetrievalDue($this->kNodeId);
        $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $wrongId],
        ]);

        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_LEARNING, $state->status);
        $this->assertSame(0, (int) $state->retention_stage, 'Reset-on-failure must be preserved.');
        $this->assertNull($state->next_review_at);
    }

    public function test_a_silent_dashboard_resolve_still_reports_a_due_check_without_logging_one(): void
    {
        DB::table('lms_concept')->where('id', $this->conceptId)
            ->update(['description' => 'Metals conduct heat and electricity.']);

        $this->setRetrievalDue($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $before = DecisionLog::forStudent($this->studentId)->count();
        $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId, silent: true);

        $this->assertSame('retrieval_due', $action['action']);
        $this->assertSame($before, DecisionLog::forStudent($this->studentId)->count(), 'A page view is not a decision.');
    }

    // ── STEP 11: mastery → enrichment → next eligible concept ────────────

    /** A second ESO-ready concept in the same chapter, with its own K/A nodes. */
    private function makeReadyConcept(string $name): array
    {
        $conceptId = $this->makeConcept($name);
        [$k, $a] = $this->makeKANodes($conceptId);

        return [$conceptId, $k, $a];
    }

    private function masterCurrentConcept(): array
    {
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.9);

        return $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
    }

    public function test_mastery_now_offers_the_next_eligible_concept_instead_of_dead_ending(): void
    {
        [$nextId] = $this->makeReadyConcept('A Later Concept');

        $action = $this->masterCurrentConcept();

        $this->assertSame('mastered_stop_practice', $action['action']);
        $this->assertNotNull($action['next_concept'], 'Mastery must open onto somewhere, not stop.');
        $this->assertSame($nextId, $action['next_concept']['concept_id']);
        $this->assertSame('A Later Concept', $action['next_concept']['name']);
        $this->assertFalse($action['chapter_complete']);

        // The enrichment key is always present so the client never has to
        // guess; empty is a legitimate value when nothing is authored.
        $this->assertIsArray($action['enrichment']);
    }

    public function test_a_concept_whose_prerequisites_are_unmet_is_never_offered_as_the_next_concept(): void
    {
        // Two candidates: one blocked behind an unmastered prerequisite, one free.
        [$blockedId] = $this->makeReadyConcept('Blocked Concept');
        [$prereqId, $prereqK, $prereqA] = $this->makeReadyConcept('Unmastered Prerequisite');
        [$freeId] = $this->makeReadyConcept('Free Concept');

        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $blockedId,
            'to_concept_id' => $prereqId,
            'relation_type' => 'requires',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The prerequisite itself is weak — well under PREREQUISITE_THRESHOLD.
        $this->setMastery($prereqK, 0.1);
        $this->setMastery($prereqA, 0.1);

        $action = $this->masterCurrentConcept();

        $this->assertNotNull($action['next_concept']);
        $this->assertNotSame(
            $blockedId,
            $action['next_concept']['concept_id'],
            'A concept locked behind an unmet prerequisite must never be offered as next.'
        );
        $this->assertContains(
            $action['next_concept']['concept_id'],
            [$prereqId, $freeId],
            'Only unblocked concepts are eligible.'
        );
    }

    public function test_no_eligible_next_concept_reports_chapter_complete_rather_than_faking_one(): void
    {
        // This concept is the only ESO-ready one in its chapter.
        $action = $this->masterCurrentConcept();

        $this->assertSame('mastered_stop_practice', $action['action']);
        $this->assertNull($action['next_concept'], 'Never invent a next concept.');
        $this->assertTrue($action['chapter_complete']);
    }

    public function test_advancing_does_not_disturb_the_retention_schedule_just_earned(): void
    {
        $this->makeReadyConcept('A Later Concept');

        $action = $this->masterCurrentConcept();
        $this->assertNotNull($action['next_concept']);

        // Mastery still schedules D5 on every node — the new advance path must
        // not clear or postpone what the verdict just set up.
        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $nodeId)->first();
            $this->assertSame(LearnerNodeState::STATUS_MASTERED, $state->status);
            $this->assertNotNull($state->next_review_at, 'Retention must stay scheduled after mastery.');
            $this->assertSame(0, (int) $state->retention_stage);
            $this->assertSame(
                now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[0])->toDateString(),
                $state->next_review_at->toDateString()
            );
        }
    }

    public function test_enrichment_writes_no_state_and_is_not_mastery_evidence(): void
    {
        $this->makeReadyConcept('A Later Concept');

        $evidenceBefore = DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('evidence_type', 'question_response')
            ->count();

        $action = $this->masterCurrentConcept();

        // No existing D1-D5 rule makes exploratory content evidence, so
        // resolving it must add none.
        $this->assertSame($evidenceBefore, DB::table('pal_learning_evidence')
            ->where('learner_id', $this->studentId)
            ->where('evidence_type', 'question_response')
            ->count());

        // And it never becomes a response of its own.
        $this->assertSame(0, DB::table('eso_response_log')
            ->where('student_id', $this->studentId)
            ->where('mode', 'enrichment')
            ->count());

        $this->assertIsArray($action['enrichment']);
    }

    public function test_the_next_concept_lookup_is_scoped_to_the_acting_student(): void
    {
        [$nextId, $nextK, $nextA] = $this->makeReadyConcept('A Later Concept');

        $otherStudentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Other',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        // The OTHER student has already mastered the follow-on concept. That
        // must not make it disappear from this student's path.
        LearnerNodeState::insert([
            ['student_id' => $otherStudentId, 'node_id' => $nextK, 'sub_institute_id' => $this->subInstituteId, 'mastery_estimate' => 1.0, 'attempts' => 3, 'status' => LearnerNodeState::STATUS_MASTERED],
            ['student_id' => $otherStudentId, 'node_id' => $nextA, 'sub_institute_id' => $this->subInstituteId, 'mastery_estimate' => 1.0, 'attempts' => 3, 'status' => LearnerNodeState::STATUS_MASTERED],
        ]);

        $action = $this->masterCurrentConcept();

        $this->assertNotNull($action['next_concept']);
        $this->assertSame(
            $nextId,
            $action['next_concept']['concept_id'],
            "Another student's progress must not alter this student's next concept."
        );
        $this->assertFalse($action['chapter_complete']);
    }

    public function test_a_silent_dashboard_resolve_does_not_recurse_or_resolve_enrichment(): void
    {
        // chapterDashboard() -> conceptStatusFor() -> masteryVerdict(silent) and
        // nextEligibleConcept() -> conceptStatusFor() would recurse without
        // bound if the silent path resolved the next concept. This test fails
        // by exhausting the stack, not by assertion, if that guard regresses.
        $this->makeReadyConcept('A Later Concept');
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.9);

        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);
        $this->assertNotNull($dashboard);

        $silent = $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId, silent: true);
        $this->assertSame([], $silent['enrichment'], 'A display-only resolve must not do post-mastery work.');
        $this->assertNull($silent['next_concept']);
    }

    // ── The data the student-facing screens actually render ──────────────

    public function test_the_chapter_dashboard_exposes_streak_badges_reviews_due_and_enrichment_state(): void
    {
        $this->setMastery($this->kNodeId, 0.5);
        $this->setMastery($this->aNodeId, 0.4);

        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $this->assertNotNull($dashboard);

        // Every one of these was computed somewhere in the estate but never
        // reached this response, so the dashboard could not render it.
        $this->assertArrayHasKey('gamification', $dashboard);
        $this->assertArrayHasKey('streak_current', $dashboard['gamification']);
        $this->assertArrayHasKey('badges_earned', $dashboard['gamification']);
        $this->assertArrayHasKey('recent_badge', $dashboard['gamification']);
        $this->assertIsInt($dashboard['gamification']['streak_current']);
        $this->assertIsInt($dashboard['gamification']['badges_earned']);

        $this->assertArrayHasKey('reviews_due', $dashboard);
        $this->assertIsInt($dashboard['reviews_due']);

        $this->assertArrayHasKey('enrichment_available', $dashboard);
        $this->assertFalse($dashboard['enrichment_available'], 'Nothing is mastered yet — there is nothing to enrich.');
    }

    public function test_the_chapter_dashboard_counts_a_review_that_is_actually_due(): void
    {
        $state = $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        $state->next_review_at = now()->subMinutes(5);
        $state->save();
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $dashboard = $this->policy->chapterDashboard($this->studentId, $this->chapterId, $this->subInstituteId);

        $this->assertSame(1, $dashboard['reviews_due'], 'A due review must be visible without opening the concept.');
    }

    public function test_mastery_details_exposes_the_two_numbers_the_d4_rule_turns_on(): void
    {
        $this->setMastery($this->kNodeId, 0.9);
        $this->setMastery($this->aNodeId, 0.75);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertNotNull($details);
        // Previously computed by masteryVerdict() and then discarded.
        $this->assertEqualsWithDelta(0.9, $details['knowledge_mastery'], 0.001);
        $this->assertEqualsWithDelta(0.75, $details['application_mastery'], 0.001);
        $this->assertSame(EsoPolicyService::KNOWLEDGE_MASTERY_THRESHOLD, $details['knowledge_threshold']);
        $this->assertSame(EsoPolicyService::APPLICATION_MASTERY_THRESHOLD, $details['application_threshold']);
        $this->assertSame(2, $details['attempts']);
    }

    public function test_mastery_details_exposes_retention_state_and_the_next_concept_once_cleared(): void
    {
        [$nextId] = $this->makeReadyConcept('A Later Concept');

        // Clear the concept for real, so retention is scheduled by the engine.
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.9);
        $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('mastered', $details['status']);
        $this->assertTrue($details['retention']['scheduled'], 'A mastered concept must show its review schedule.');
        $this->assertFalse($details['retention']['due_now'], 'The first rung is two days out, not now.');
        $this->assertSame(0, $details['retention']['stage']);
        $this->assertNotNull($details['retention']['next_review_at']);

        $this->assertNotNull($details['next_concept']);
        $this->assertSame($nextId, $details['next_concept']['concept_id']);
        $this->assertIsArray($details['enrichment']);
    }

    public function test_mastery_details_offers_no_next_concept_or_enrichment_before_the_concept_is_cleared(): void
    {
        $this->makeReadyConcept('A Later Concept');

        $this->setMastery($this->kNodeId, 0.3);
        $this->setMastery($this->aNodeId, 0.3);

        $details = $this->policy->conceptMasteryDetails($this->studentId, $this->conceptId, $this->subInstituteId);

        $this->assertSame('in_progress', $details['status']);
        $this->assertNull($details['next_concept'], 'Do not offer an exit the student has not earned.');
        $this->assertSame([], $details['enrichment']);
        $this->assertFalse($details['retention']['scheduled']);
    }

    // ── Gamification: ESO outcomes feed the EXISTING badge system ───────

    public function test_reaching_d4_mastery_awards_the_adaptive_learning_badge_from_the_existing_gamification_system(): void
    {
        $badges = app(\App\Services\PAL\Gamification\BadgeService::class);

        // Nothing earned before the concept is mastered.
        $this->assertSame(
            0,
            collect($badges->evaluate($this->studentId))->where('badge_id', 'BADGE_ADAPTIVE_FIRST_MASTERY')->count()
        );

        $this->setMastery($this->kNodeId, 0.85);
        $this->setMastery($this->aNodeId, 0.75);
        $verdict = $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);
        $this->assertTrue($verdict['mastered']);

        // The engine logged `mastered_stop_practice`, which is the evidence
        // LearnerActivitySource::esoConceptsMastered() reads — so a re-check
        // now finds the badge earned. (masteryVerdict() already nudged
        // evaluate() itself; calling again is idempotent and returns what is
        // newly earned at this point, so assert against the persisted set.)
        $badges->evaluate($this->studentId);
        $earned = DB::table('pal_learner_badges')
            ->where('learner_id', $this->studentId)
            ->where('badge_id', 'BADGE_ADAPTIVE_FIRST_MASTERY')
            ->exists();

        $this->assertTrue($earned, 'D4 mastery must be recognised by the existing badge system, not a new currency.');
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

        // Evidence the student can check the call against: the answer they
        // actually picked, and that this is the first time (not a repeat).
        $this->assertSame('A wrong option', $result['evidence']['chosen_answer']);
        $this->assertSame(0, $result['evidence']['previous_occurrences']);
        $this->assertSame('Confuses X with Y.', $result['misconception_description']);

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

    /**
     * D5 now schedules against RETENTION_LADDER_DAYS rather than one flat
     * interval — mastery enters the ladder at its FIRST rung (Day 2), and
     * each passed check climbs to a longer one (see the ladder tests below).
     */
    public function test_mastery_schedules_the_first_rung_of_the_retention_ladder(): void
    {
        $this->setMastery($this->kNodeId, 0.85);
        $this->setMastery($this->aNodeId, 0.75);

        $this->policy->masteryVerdict($this->studentId, $this->conceptId, $this->subInstituteId);

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();

        // Compare the scheduled DATE, not a fractional-day diff — diffInDays()
        // truncates, so "2 days out" reads as 1 once any time has elapsed.
        $this->assertSame(
            now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[0])->toDateString(),
            $state->next_review_at->toDateString()
        );
        $this->assertSame(0, (int) $state->retention_stage, 'Mastery enters the ladder at stage 0; only a passed check climbs it.');
    }

    public function test_each_passed_retrieval_check_climbs_one_rung_of_the_retention_ladder(): void
    {
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        // First passed check: stage 0 -> 1, so the next review uses rung 1 (a week).
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $this->makeAnswer(true)],
        ]);

        $state = LearnerNodeState::where('student_id', $this->studentId)->where('node_id', $this->kNodeId)->first();
        $this->assertSame(LearnerNodeState::STATUS_RETAINED, $state->status);
        $this->assertSame(1, (int) $state->retention_stage);
        $this->assertSame(
            now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[1])->toDateString(),
            $state->next_review_at->toDateString()
        );

        // Second passed check: stage 1 -> 2, next review uses rung 2 (a month).
        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $this->makeAnswer(true)],
        ]);

        $state->refresh();
        $this->assertSame(2, (int) $state->retention_stage);
        $this->assertSame(
            now()->addDays(EsoPolicyService::RETENTION_LADDER_DAYS[2])->toDateString(),
            $state->next_review_at->toDateString()
        );
    }

    public function test_the_ladder_stops_scheduling_once_its_last_rung_is_passed(): void
    {
        $lastStage = count(EsoPolicyService::RETENTION_LADDER_DAYS) - 1;
        $state = $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        $state->retention_stage = $lastStage;
        $state->save();

        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $this->makeAnswer(true)],
        ]);

        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_RETAINED, $state->status);
        $this->assertNull($state->next_review_at, 'Past the last rung the ladder is complete — no further review is scheduled.');
    }

    public function test_a_failed_retrieval_check_resets_the_retention_ladder_to_the_start(): void
    {
        $state = $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);
        $state->retention_stage = 3; // deep into the ladder
        $state->save();

        $this->policy->retrievalCheck($this->studentId, $this->kNodeId, $this->conceptId, $this->subInstituteId, [
            ['answer_master_id' => $this->makeAnswer(false)],
        ]);

        $state->refresh();
        $this->assertSame(LearnerNodeState::STATUS_LEARNING, $state->status);
        $this->assertSame(0, (int) $state->retention_stage, 'A failed check restarts the ladder rather than resuming a long interval it has not earned.');
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
