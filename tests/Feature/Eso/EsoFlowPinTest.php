<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use App\Services\PAL\Flow\EsoFlowRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A learner keeps the flow they started under.
 *
 * ---------------------------------------------------------------------------
 * WHAT THE PIN IS FOR
 * ---------------------------------------------------------------------------
 * Once a school's flow is configurable, publishing a new profile version would
 * otherwise re-rule learners who are part-way through a concept — taught under
 * one flow, assessed under another. learner_node_state.flow_version_id is what
 * stops that: it is stamped when a node's state is created and never touched
 * again.
 *
 * pal_architecture_settings, the per-institute overlay this estate already has,
 * carries no version column and therefore does exactly the thing this column
 * exists to prevent.
 *
 * ---------------------------------------------------------------------------
 * WHY THESE TESTS LOOK AT BEHAVIOUR AND NOT JUST THE COLUMN
 * ---------------------------------------------------------------------------
 * Asserting "the column holds 7" proves the write worked and nothing else. The
 * tests below pin a learner to a version whose flow is genuinely DIFFERENT
 * (`no_cfu`, which switches the check phase off) and assert the engine serves
 * them that flow instead of their institute's current one. That is the promise
 * the column makes, and it is the only thing worth asserting.
 *
 * Skipped wholesale when the flow tables are absent — 408 of this estate's 996
 * migrations are pending, so a host without them is the normal case, not a
 * broken one.
 */
class EsoFlowPinTest extends TestCase
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

    private int $standardVersionId;

    private int $noCfuVersionId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('learner_node_state', 'flow_version_id')) {
            $this->markTestSkipped('flow_version_id is not present on this connection.');
        }

        $registry = app(EsoFlowRegistry::class);

        if (! $registry->available()) {
            $this->markTestSkipped('The PAL flow tables are not present on this connection.');
        }

        $standard = $registry->activeVersion('standard');
        $noCfu = $registry->activeVersion('no_cfu');

        if ($standard === null || $noCfu === null) {
            $this->markTestSkipped('The shipped flow profiles have not been seeded on this connection.');
        }

        $this->standardVersionId = $standard['id'];
        $this->noCfuVersionId = $noCfu['id'];

        // The pin only governs under the pipeline engine; the legacy cascade
        // has no notion of a flow version.
        config(['pal_flow.guards.engine' => 'pipeline']);

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Pin School',
            'ShortCode' => 'ESOP' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-pin@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-pin@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Pinned',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Pin Subject ' . random_int(1000, 9999),
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
            'chapter_name' => 'ESO Pin Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'ESO Pin Concept',
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        [$this->kNodeId, $this->aNodeId] = $this->makeKANodes($this->conceptId);
    }

    // ══════════════════════════════════════════════════════════════════════
    // The stamp
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The acceptance criterion for this step.
     *
     * Guards two silent no-ops at once. If `flow_version_id` were missing from
     * LearnerNodeState::$fillable, firstOrCreate() would drop it without error.
     * If currentFlowVersionId() read only $flowPlan, the WRITE paths — which
     * is where a learner's first state row is actually created — would stamp
     * null. Either way every row in production comes back unpinned and nothing
     * complains.
     */
    public function test_a_newly_created_state_row_is_pinned(): void
    {
        $state = $this->policy->stateFor($this->studentId, $this->kNodeId, $this->subInstituteId);

        $this->assertNotNull(
            $state->flow_version_id,
            'A state row created under the pipeline engine must carry the flow version it started under.'
        );
        $this->assertSame($this->standardVersionId, (int) $state->flow_version_id);
    }

    /**
     * The write paths are where this actually happens.
     *
     * A learner's very first state row for a concept is almost always created
     * by scoreDiagnostic(), because the cold-start resolve returns `diagnostic`
     * before the node loop ever asks for a state. A pin that only worked inside
     * a resolve would be null for nearly every real learner.
     */
    public function test_the_diagnostic_write_path_pins_the_rows_it_creates(): void
    {
        $this->assertSame(0, LearnerNodeState::forStudent($this->studentId)->count());

        $this->policy->scoreDiagnostic($this->studentId, $this->conceptId, $this->subInstituteId, [
            ['node_id' => $this->kNodeId, 'answer_master_id' => $this->makeAnswer(false)],
        ]);

        $state = LearnerNodeState::forStudent($this->studentId)->where('node_id', $this->kNodeId)->firstOrFail();

        $this->assertSame($this->standardVersionId, (int) $state->flow_version_id);
    }

    /** Stamped at creation, never on update — or it would not be a pin. */
    public function test_the_pin_is_never_rewritten_after_creation(): void
    {
        $state = $this->policy->stateFor($this->studentId, $this->kNodeId, $this->subInstituteId);
        $state->flow_version_id = $this->noCfuVersionId;
        $state->save();

        // Resolve again. firstOrCreate() finds the existing row, and its
        // defaults must not be reapplied.
        $again = $this->policy->stateFor($this->studentId, $this->kNodeId, $this->subInstituteId);

        $this->assertSame(
            $this->noCfuVersionId,
            (int) $again->flow_version_id,
            'An existing row keeps its pin; only creation stamps one.'
        );
    }

    /** Under the legacy cascade there is no versioned flow to pin to. */
    public function test_the_legacy_engine_leaves_the_pin_null(): void
    {
        config(['pal_flow.guards.engine' => 'legacy']);

        $state = app(EsoPolicyService::class)
            ->stateFor($this->studentId, $this->kNodeId, $this->subInstituteId);

        $this->assertNull(
            $state->flow_version_id,
            'A node resolved by the hardcoded cascade is not running a versioned flow, and '
            . 'claiming otherwise would put a number in the column that never governed anything.'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // What the pin actually does
    // ══════════════════════════════════════════════════════════════════════

    /**
     * A pinned learner is served the flow they started under, not their
     * institute's current one.
     *
     * Observable rather than asserted on the column: `no_cfu` switches the
     * check phase off, so a node that has practised to its threshold with the
     * evidence floor met and a CFU question authored resolves
     * `check_understanding` under `standard` and does NOT under `no_cfu`.
     */
    public function test_a_learner_pinned_to_another_version_is_served_that_flow(): void
    {
        $this->seedNodeAwaitingItsCheck();

        // Unpinned: the institute's current flow, which serves the check.
        $this->assertSame(
            'check_understanding',
            $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId)['action'],
            'Baseline: the standard flow serves the check.'
        );

        $this->pinAllNodesTo($this->noCfuVersionId);

        $this->assertNotSame(
            'check_understanding',
            app(EsoPolicyService::class)->nextAction($this->studentId, $this->conceptId, $this->subInstituteId)['action'],
            'A learner pinned to no_cfu must not be served a check, however their institute is configured now.'
        );
    }

    /**
     * Mixed pins resolve to the OLDEST.
     *
     * The column's grain is per node, which is finer than the pin means, so a
     * concept can carry two versions across its nodes — a learner who started
     * one node, had a new version published, then started a sibling.
     *
     * Taking the minimum makes that deterministic AND monotone: a learner can
     * only be pinned backward mid-concept, never forward, so publishing a new
     * version can never tighten the rules under someone part-way through.
     * `standard` was seeded first and therefore holds the lower id.
     */
    public function test_mixed_pins_across_a_concept_resolve_to_the_oldest(): void
    {
        $this->seedNodeAwaitingItsCheck();

        $this->assertLessThan(
            $this->noCfuVersionId,
            $this->standardVersionId,
            'This test depends on standard being the older version.'
        );

        $this->pinNodeTo($this->kNodeId, $this->noCfuVersionId);
        $this->pinNodeTo($this->aNodeId, $this->standardVersionId);

        $this->assertSame(
            'check_understanding',
            app(EsoPolicyService::class)->nextAction($this->studentId, $this->conceptId, $this->subInstituteId)['action'],
            'The oldest pin governs the whole concept, so the standard flow applies.'
        );
    }

    // ── fixtures ─────────────────────────────────────────────────────────

    /**
     * A K node that has practised to its threshold with the floor met and a
     * check question authored — the one state where enabling or disabling the
     * check phase changes what is served.
     */
    private function seedNodeAwaitingItsCheck(): void
    {
        $this->makeServableQuestion($this->kNodeId);

        $taughtAt = now()->subMinutes(10);

        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            $this->seedEvidence($nodeId, at: now()->subMinutes(5));

            LearnerNodeState::updateOrCreate(
                ['student_id' => $this->studentId, 'node_id' => $nodeId],
                [
                    'sub_institute_id' => $this->subInstituteId,
                    'mastery_estimate' => 1.0,
                    'attempts' => 3,
                    'status' => LearnerNodeState::STATUS_LEARNING,
                    'last_seen_at' => now(),
                    'taught_at' => $taughtAt,
                    'cfu_passed_at' => null,
                    'cfu_attempts' => 0,
                ]
            );
        }
    }

    private function pinAllNodesTo(int $versionId): void
    {
        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            $this->pinNodeTo($nodeId, $versionId);
        }
    }

    /**
     * Written through the model, never the query builder.
     *
     * LearnerNodeState::$writeVersion is a static cache invalidator whose
     * correctness rests on every write going through the model (its docblock,
     * lines 46-76). A DB::table()->update() here would leave
     * EsoPolicyService::learnerStates() serving the pre-pin rows from memory
     * and the test would assert against state the engine cannot see.
     */
    private function pinNodeTo(int $nodeId, int $versionId): void
    {
        $state = LearnerNodeState::forStudent($this->studentId)->where('node_id', $nodeId)->firstOrFail();
        $state->flow_version_id = $versionId;
        $state->save();
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

    private function seedEvidence(int $nodeId, ?\Illuminate\Support\Carbon $at = null): void
    {
        $at ??= now();

        for ($i = 0; $i < EsoPolicyService::MIN_EVENTS_K; $i++) {
            DB::table('eso_response_log')->insert([
                'student_id' => $this->studentId,
                'concept_id' => $this->conceptId,
                'node_id' => $nodeId,
                'sub_institute_id' => $this->subInstituteId,
                'question_id' => (900000 + $nodeId * 100 + $i),
                'correct' => true,
                'hint_used' => false,
                'mode' => LearnerNodeState::MODE_INDEPENDENT,
                // Explicit: the MariaDB host clock runs ~2.5h behind PHP, and
                // practiceComplete() compares this against taught_at.
                'created_at' => $at,
            ]);
        }
    }

    private function makeAnswer(bool $correct): int
    {
        return (int) DB::table('answer_master')->insertGetId([
            'question_id' => 990000 + random_int(1, 9999),
            'answer' => $correct ? 'The correct option' : 'A wrong option',
            'correct_answer' => $correct ? 1 : 0,
            'sub_institute_id' => $this->subInstituteId,
            'created_on' => now(),
        ]);
    }

    private function makeServableQuestion(int $nodeId): int
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1,
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'ESO pin question',
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
            'quality_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $questionId;
    }
}
