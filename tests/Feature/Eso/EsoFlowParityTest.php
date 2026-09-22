<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use App\Services\PAL\Flow\EsoFlowRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Parity harness for the per-institute flow refactor (rollout steps 1-4).
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS FOR
 * ---------------------------------------------------------------------------
 * EsoPolicyService::nextAction() is a PRIORITY-ORDERED GUARD CASCADE, not a
 * pipeline. Its ordering encodes correctness: the D3 hoist at
 * EsoPolicyService.php:1067-1078 documents a live bug caused by precedence
 * accidentally depending on pal_concept_nodes.sort_order.
 *
 * EsoPolicyServiceTest's 146 tests assert individual RULES. None of them
 * asserts CASCADE ORDER end to end, which is precisely the property the
 * stage-extraction refactor can break. So "146 green" is necessary and not
 * sufficient, and this file covers the gap: one scenario per `return` site in
 * the current cascade, comparing the whole observable output rather than one
 * field of it.
 *
 * ---------------------------------------------------------------------------
 * WHY IT COMPARES TWO RUNS IN ONE PROCESS
 * ---------------------------------------------------------------------------
 * The suite runs against the SHARED REMOTE dev database (phpunit.xml has its
 * sqlite lines commented out at 24-25, and there is no .env.testing, so
 * DB_CONNECTION falls through to .env — vivek_erp at 202.47.117.220). A golden
 * fixture captured on one run and compared on another would be worthless: the
 * database drifts under it, and the MariaDB host clock runs ~2.5h behind PHP
 * (documented on App\Models\Eso\ResponseLog, lines 20-32), so even now()
 * differs between the two sides.
 *
 * Therefore both halves run in ONE process, inside ONE transaction, against
 * ONE fixture and ONE clock. That is the only comparison that means anything
 * here.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT ASSERTS AT STEP 1 (today) VS STEP 3 (after extraction)
 * ---------------------------------------------------------------------------
 * There is no pipeline engine yet. At step 1 both halves run the CURRENT
 * engine, which makes this a test of the HARNESS rather than of the refactor —
 * and that is deliberately the point. snapshot/restore is the hard part: if
 * restoreStates() leaks a stamped taught_at or a swept `mastered` status from
 * run 1 into run 2, a real parity failure would later be indistinguishable
 * from harness noise. Proving determinism against the unmodified engine NOW
 * means that at step 3 a red test can only mean the pipeline diverged.
 *
 * At step 3, resolveEngine() gains the 'pipeline' branch and the second half
 * switches to it. Nothing else in this file changes.
 *
 * Deleted at rollout step 9, together with nextActionLegacy().
 *
 * Fixture helpers are copied from EsoPolicyServiceTest rather than shared —
 * the same copy-paste convention EsoChapterDashboardTest, EsoKnowledgeMapTest
 * and EsoConceptMasteryDetailsTest already follow and state in their docblocks.
 */
class EsoFlowParityTest extends TestCase
{
    use DatabaseTransactions;

    /** Estimates at or above the lower gate imply the node was genuinely demonstrated. */
    private const EVIDENCE_SEED_FROM = EsoPolicyService::APPLICATION_MASTERY_THRESHOLD;

    /**
     * Queries the pipeline is ALLOWED to spend that the legacy cascade does not.
     *
     * Exactly one: EsoFlowRegistry::flowFor() reads the institute's assigned
     * profile and its active version's structure in a single statement. That is
     * the irreducible price of the flow being data rather than code — the
     * engine cannot know which flow to run without asking.
     *
     * Budgeted rather than waived. An unbounded `<=` would let the cost creep a
     * query at a time with nothing to notice it; pinning it at one means the
     * second query anyone adds to the resolve path fails this suite.
     *
     * NOT included: the three Schema::hasTable() probes behind
     * EsoFlowRegistry::available(). Those are cached statically for the life of
     * the process, so they are a startup constant and not a per-resolve cost.
     * runCapture() warms them before measuring, for both engines equally —
     * otherwise whichever scenario happened to run first would carry a +3 that
     * has nothing to do with it.
     */
    private const FLOW_RESOLUTION_QUERIES = 1;

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

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Flow Parity School',
            'ShortCode' => 'ESOF' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-parity@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-parity@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Parity',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Parity Subject ' . random_int(1000, 9999),
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
            'chapter_name' => 'ESO Parity Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = $this->makeConcept('ESO Parity Concept');
        [$this->kNodeId, $this->aNodeId] = $this->makeKANodes($this->conceptId);
    }

    // ── The parity test ──────────────────────────────────────────────────

    /**
     * Every `return` site in the cascade, compared on its whole observable
     * output rather than one field.
     *
     * The provider is bound by attribute rather than by the `@dataProvider`
     * doc-comment EsoPolicyServiceTest uses at line 2616. Same semantics, but
     * PHPUnit 11.5 already reports doc-comment metadata as deprecated and
     * PHPUnit 12 drops it, and this file outlives the refactor it was written
     * for. The rest of the file follows house style.
     */
    #[DataProvider('cascadeScenarios')]
    public function test_the_cascade_resolves_identically_across_engines(string $scenario, bool $silent): void
    {
        $conceptId = $this->buildScenario($scenario);

        $before = $this->snapshotStates();
        $logWatermark = $this->decisionLogWatermark();

        $legacy = $this->runCapture('legacy', $conceptId, $silent, $logWatermark);

        // Before comparing anything, prove the fixture reached the branch it
        // claims to. Without this the suite degrades silently: a fixture that
        // stops resolving what it was written for still compares two identical
        // runs of the WRONG branch and stays green, and the `return` site it
        // was guarding quietly loses its only coverage.
        $this->assertReachedItsBranch($scenario, $legacy['return']);

        $this->restoreStates($before, $logWatermark);

        $candidate = $this->runCapture($this->candidateEngine(), $conceptId, $silent, $logWatermark);

        // 1. The returned payload, whole and in key order. This is serialised
        //    straight to JSON by EsoEngineController::nextAction(), so key
        //    order is part of the API contract, not an implementation detail.
        //    assertSame on arrays is `===`, which compares order and types.
        $this->assertSame(
            $legacy['return'],
            $candidate['return'],
            "Scenario '{$scenario}': the resolved action differs between engines."
        );

        // 2. The decision-log rows written, AND their sequence. A refactor that
        //    resolves the right action by the wrong route shows up here and
        //    nowhere else — rule_fired is the only record of which guard won.
        $this->assertEquals(
            $legacy['decisions'],
            $candidate['decisions'],
            "Scenario '{$scenario}': the audit trail differs — same answer, different reasoning."
        );

        // 3. Learner state after the resolve. nextAction() is not read-only:
        //    teachAction() stamps taught_at, masteryVerdict() sweeps status to
        //    `mastered` and calls scheduleRetention(). Microsecond timestamps
        //    are excluded (see stateFingerprint); nothing else is.
        $this->assertEquals(
            $legacy['states'],
            $candidate['states'],
            "Scenario '{$scenario}': learner state diverged."
        );

        // 4. Query count. THIS IS THE ASSERTION THAT CATCHES THE MOST LIKELY
        //    SILENT REGRESSION. nextAction() resolves evidenceByNode() and
        //    isConceptStale() ONCE for the whole concept (lines 1061-1065) and
        //    threads them through the node loop. A stage that re-resolves
        //    either because the context was not threaded is still CORRECT — it
        //    returns the same action — and would pass assertions 1-3 while
        //    quietly multiplying round trips. On a remote database where
        //    memo()'s own docblock (4407-4423) measures ~29ms per round trip
        //    and describes collapsing 168 queries to 2 as the whole point,
        //    that is a product regression.
        $this->assertLessThanOrEqual(
            $legacy['queries'] + self::FLOW_RESOLUTION_QUERIES,
            $candidate['queries'],
            sprintf(
                "Scenario '%s': the candidate engine issued %d queries against the legacy engine's %d, "
                . 'a budget of %d being allowed for reading the institute\'s flow. '
                . 'A stage is probably re-resolving evidence the context already carries.',
                $scenario,
                $candidate['queries'],
                $legacy['queries'],
                self::FLOW_RESOLUTION_QUERIES
            )
        );
    }

    /**
     * One case per `return` site in nextAction(), plus the silent variants.
     *
     * Scenario 5 is the important one. It is the exact shape the D3 hoist at
     * lines 1067-1078 was written to fix — a flagged node sorting AFTER a node
     * with a due retrieval — and re-flattening the cascade into a simple
     * ordered list is the most likely way to reintroduce it.
     *
     * @return array<string, array{0:string, 1:bool}>
     */
    public static function cascadeScenarios(): array
    {
        $scenarios = [
            'no_nodes_defined'              => 'concept with no K/A/S nodes authored',
            'diagnostic_entry'              => 'no learner state at all — D1 entry',
            'prerequisite_remediation'      => 'prerequisite below threshold — D2 gate',
            'prerequisite_stale_probe'      => 'prerequisite passing but evidence stale',
            'misconception_outranks_retrieval' => 'D3 hoist: flagged node sorts after a due node',
            'retrieval_due'                 => 'mastered node past next_review_at',
            'retrieval_content_unavailable' => 'retrieval due but no item authored',
            'stale_mastery'                 => 'all mastered, no schedule, evidence stale',
            'settled_skip_serves_sibling'   => 'saturated node skipped, sibling served',
            'phase_teach'                   => 'untaught node',
            'phase_practice'                => 'taught, short of the evidence floor',
            'phase_check'                   => 'practice complete, check still open',
            'phase_reteach'                 => 'a failed check reopened Learn',
            'content_unavailable_holdback'  => 'first node unservable, second servable',
            'content_unavailable_terminal'  => 'nothing in the concept is servable',
            'mastery_verdict'               => 'every node already mastered, verdict confirms it',
            'mastery_transition'            => 'every node earns mastery on this resolve',
        ];

        $cases = [];

        foreach ($scenarios as $key => $description) {
            $cases[$description] = [$key, false];
        }

        // Scenario 14: every scenario again on the silent path. The silent
        // path is not a cosmetic flag — masteryVerdict() gates enrichment and
        // next-concept resolution on `! $silent` because chapterDashboard()
        // reaches it through conceptStatusFor(), and resolving them there
        // would recurse without bound (the docblock at 2549-2557). A stage
        // that drops $silent turns a dashboard load into a runaway.
        foreach ($scenarios as $key => $description) {
            $cases[$description . ' [silent]'] = [$key, true];
        }

        return $cases;
    }

    /**
     * The silent path writes no audit trail at all.
     *
     * Asserted separately from the parity comparison because it is a property
     * of the CURRENT engine that must survive the refactor, not a comparison
     * between two engines — if both engines wrote a row on the silent path,
     * parity would be green and the behaviour still wrong.
     */
    #[DataProvider('cascadeScenarios')]
    public function test_a_silent_resolve_writes_no_decision_log_row(string $scenario, bool $silent): void
    {
        if (! $silent) {
            $this->markTestSkipped('Covered by the silent half of the provider.');
        }

        $conceptId = $this->buildScenario($scenario);
        $watermark = $this->decisionLogWatermark();

        $this->engine()->nextAction($this->studentId, $conceptId, $this->subInstituteId, silent: true);

        $this->assertSame(
            [],
            $this->decisionsSince($watermark),
            "Scenario '{$scenario}': a silent resolve must not write to eso_decision_log."
        );
    }

    /**
     * The branch each scenario exists to cover, as `action` plus the `D`-rule
     * that must have fired.
     *
     * `rule_fired` is checked as well as `action` because two scenarios can
     * share an action and still be different branches — `retrieval_due` is
     * reached from the SCHEDULED path (D5, line 1103) and from the
     * STALE-MASTERY path (D2, line 1116), and only rule_fired tells them
     * apart. Matching on action alone would let either fixture silently cover
     * both and neither.
     *
     * @param  array<string,mixed>  $resolved
     */
    private function assertReachedItsBranch(string $scenario, array $resolved): void
    {
        [$action, $rule] = match ($scenario) {
            'no_nodes_defined'                 => ['no_nodes_defined', 'ESO:'],
            'diagnostic_entry'                 => ['diagnostic', 'D1'],
            'prerequisite_remediation'         => ['remediate_prerequisite', 'D2'],
            'prerequisite_stale_probe'         => ['prerequisite_quick_probe', 'D2'],
            'misconception_outranks_retrieval' => ['serve_contrast_pair', 'D3'],
            'retrieval_due'                    => ['retrieval_due', 'D5'],
            'stale_mastery'                    => ['retrieval_due', 'D2'],
            'settled_skip_serves_sibling'      => ['teach', 'D1'],
            'phase_teach'                      => ['teach', 'D1'],
            'phase_practice'                   => ['practice', 'D4'],
            'phase_check'                      => ['check_understanding', 'D1-CFU'],
            'phase_reteach'                    => ['reteach', 'D1'],
            'retrieval_content_unavailable'    => ['content_unavailable', 'D5'],
            'content_unavailable_holdback'     => ['teach', 'D1'],
            'content_unavailable_terminal'     => ['content_unavailable', ''],
            'mastery_verdict'                  => ['mastered_stop_practice', 'D4'],
            'mastery_transition'               => ['mastered_stop_practice', 'D4'],
            default => throw new \InvalidArgumentException("No expected branch declared for '{$scenario}'."),
        };

        $this->assertSame(
            $action,
            $resolved['action'] ?? null,
            "Scenario '{$scenario}' no longer reaches the branch it was written to cover."
        );

        if ($rule !== '') {
            $this->assertStringStartsWith(
                $rule,
                (string) ($resolved['rule_fired'] ?? ''),
                "Scenario '{$scenario}' resolved the right action by the wrong rule."
            );
        }

        // The skip at line 1141 is invisible in `action` — both it and the
        // plain teach path return `teach`. The proof it fired is WHICH node
        // got served: K is saturated and must be passed over for A.
        if ($scenario === 'settled_skip_serves_sibling') {
            $this->assertSame(
                $this->aNodeId,
                (int) ($resolved['node_id'] ?? 0),
                'The saturated K node must be skipped and the A sibling served.'
            );
        }
    }

    // ── Engine selection ─────────────────────────────────────────────────

    /**
     * Which engine the second half runs.
     *
     * At step 1 this was 'legacy', so the test compared the engine with itself
     * and proved the snapshot/restore harness deterministic before it had to
     * prove anything about new code. From step 3 it is 'pipeline', and this
     * file is doing its real job: every scenario below now runs the hardcoded
     * cascade and the stage pipeline over the same fixture and demands they
     * agree on all four observable outputs.
     */
    private function candidateEngine(): string
    {
        return 'pipeline';
    }

    /**
     * Resolve a FRESH service instance and point it at the named engine.
     *
     * Fresh matters: EsoPolicyService is deliberately not registered in
     * PALServiceProvider (see its comment at lines 94-100 — eager binding
     * reopens a container cycle through PedagogySelectorEngine), so it is
     * autowired and app() hands back a new instance each call. That is what
     * clears $practicePoolSizes (line 191), $requestMemo (4427) and
     * $learnerStateCache (4485) between the two halves — forgetMemoized()
     * cannot be used for this today because it clears only the first of the
     * three (and has no callers at all).
     */
    private function engine(string $name = 'legacy'): EsoPolicyService
    {
        config(['pal_flow.guards.engine' => $name]);

        return app(EsoPolicyService::class);
    }

    /**
     * Run one resolve and capture everything observable about it.
     *
     * @return array{return: array<string,mixed>, decisions: array<int,array<string,mixed>>, states: array<int,array<string,mixed>>, queries: int}
     */
    private function runCapture(string $engineName, int $conceptId, bool $silent, int $logWatermark): array
    {
        $policy = $this->engine($engineName);

        // Warm the per-process schema probe before measuring. See
        // FLOW_RESOLUTION_QUERIES.
        app(EsoFlowRegistry::class)->available();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $return = $policy->nextAction($this->studentId, $conceptId, $this->subInstituteId, silent: $silent);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [
            'return' => $return,
            'decisions' => $this->decisionsSince($logWatermark),
            'states' => $this->stateFingerprint(),
            'queries' => $queries,
        ];
    }

    // ── Snapshot / restore ───────────────────────────────────────────────

    /**
     * Every learner_node_state row for this student, as raw attributes.
     *
     * @return array<int, array<string,mixed>> keyed by node_id
     */
    private function snapshotStates(): array
    {
        return LearnerNodeState::forStudent($this->studentId)
            ->get()
            ->keyBy('node_id')
            ->map(fn (LearnerNodeState $s) => $s->getAttributes())
            ->all();
    }

    /**
     * Put the learner back exactly where they were before the first resolve.
     *
     * Two rules govern this method, and breaking either one makes every parity
     * result meaningless:
     *
     *   1. WRITES GO THROUGH THE MODEL, never DB::table()->update(). The
     *      docblock on LearnerNodeState (lines 46-60) explains that its static
     *      $writeVersion cache invalidator is complete ONLY because every write
     *      in app/ goes through the model. A query-builder update here would
     *      skip the saved/deleted events, leave EsoPolicyService::learnerStates()
     *      (4451) serving a stale cache into the second half, and produce a
     *      parity failure that has nothing to do with the refactor.
     *
     *   2. ROWS CREATED BY THE FIRST RESOLVE ARE DELETED, not just reset.
     *      stateFor() (4386) firstOrCreate's a row for any node it touches, so
     *      the first resolve can leave rows the fixture never seeded. Leaving
     *      them behind would hand the second half a learner who has already
     *      been through the cascade once.
     *
     * @param  array<int, array<string,mixed>>  $snapshot
     */
    private function restoreStates(array $snapshot, int $logWatermark): void
    {
        foreach (LearnerNodeState::forStudent($this->studentId)->get() as $state) {
            $nodeId = (int) $state->node_id;

            if (! array_key_exists($nodeId, $snapshot)) {
                $state->delete();   // model event fires; $writeVersion bumps
                continue;
            }

            // setRawAttributes + save restores the row byte-for-byte, including
            // the nulls the cascade may have stamped over (taught_at,
            // next_review_at) which a fillable-only assignment would miss.
            $state->setRawAttributes($snapshot[$nodeId]);
            $state->save();
        }

        // The second half must see a clean audit trail, or decisionsSince()
        // returns the first half's rows concatenated with its own.
        DecisionLog::where('id', '>', $logWatermark)
            ->where('student_id', $this->studentId)
            ->delete();
    }

    /**
     * Learner state, reduced to what parity actually means.
     *
     * Excludes ONLY the wall-clock stamps, and only because the two halves run
     * microseconds apart: created_at, updated_at, taught_at, last_seen_at.
     * Everything a policy decision can turn on — status, mastery_estimate,
     * attempts, consecutive_correct, cfu_attempts, cfu_passed_at,
     * retention_stage, active_misconception_id — is compared exactly.
     *
     * next_review_at is compared to DATE precision rather than dropped:
     * scheduleRetention() sets it from RETENTION_LADDER_DAYS, so the rung a
     * learner lands on is a policy outcome and must match, while the
     * sub-second offset between two runs must not.
     *
     * @return array<int, array<string,mixed>> keyed by node_id
     */
    private function stateFingerprint(): array
    {
        return LearnerNodeState::forStudent($this->studentId)
            ->orderBy('node_id')
            ->get()
            ->keyBy('node_id')
            ->map(fn (LearnerNodeState $s) => [
                'status' => $s->status,
                'mastery_estimate' => (float) $s->mastery_estimate,
                'attempts' => (int) $s->attempts,
                'consecutive_correct' => (int) $s->consecutive_correct,
                'practice_mode' => $s->practice_mode,
                'hint_used_count' => (int) $s->hint_used_count,
                'cfu_attempts' => (int) $s->cfu_attempts,
                'cfu_passed' => $s->cfu_passed_at !== null,
                'retention_stage' => (int) $s->retention_stage,
                'active_misconception_id' => $s->active_misconception_id === null ? null : (int) $s->active_misconception_id,
                'taught' => $s->taught_at !== null,
                'next_review_on' => $s->next_review_at?->toDateString(),
            ])
            ->all();
    }

    private function decisionLogWatermark(): int
    {
        return (int) (DecisionLog::max('id') ?? 0);
    }

    /**
     * Decision rows written since the watermark, in write order.
     *
     * id and created_at are excluded — they differ between two runs by
     * construction. Everything that records WHY a decision was made is kept.
     *
     * @return array<int, array<string,mixed>>
     */
    private function decisionsSince(int $watermark): array
    {
        return DecisionLog::where('id', '>', $watermark)
            ->where('student_id', $this->studentId)
            ->orderBy('id')
            ->get()
            ->map(fn (DecisionLog $d) => [
                'concept_id' => $d->concept_id === null ? null : (int) $d->concept_id,
                'node_id' => $d->node_id === null ? null : (int) $d->node_id,
                'rule_fired' => $d->rule_fired,
                'action' => $d->action,
                'llm_instruction' => $d->llm_instruction,
                'state_snapshot' => $this->normaliseSnapshot($d->state_snapshot),
            ])
            ->all();
    }

    /**
     * Blunt wall-clock stamps inside a decision's state_snapshot to the day.
     *
     * Some snapshots carry the CURRENT time rather than learner state — the
     * D5 retrieval branch writes `['next_review_at' => now()->toDateTimeString()]`
     * at EsoPolicyService.php:2833, despite the key's name. The two halves of
     * a parity run are milliseconds apart and will straddle a second boundary
     * roughly one run in three, so comparing these to the second fails on the
     * clock rather than on behaviour.
     *
     * Blunted to the DAY, not dropped: which day a decision recorded is still
     * a policy outcome (scheduleRetention() picks it from
     * RETENTION_LADDER_DAYS), while the second within that day is not.
     *
     * @param  mixed  $snapshot
     * @return mixed
     */
    private function normaliseSnapshot(mixed $snapshot): mixed
    {
        if (! is_array($snapshot)) {
            return $snapshot;
        }

        foreach ($snapshot as $key => $value) {
            if (is_array($value)) {
                $snapshot[$key] = $this->normaliseSnapshot($value);
                continue;
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $value) === 1) {
                $snapshot[$key] = substr($value, 0, 10);
            }
        }

        return $snapshot;
    }

    // ── Scenario builders ────────────────────────────────────────────────

    /**
     * Seed one scenario and return the concept to resolve against.
     *
     * Built here rather than in the data provider because providers run before
     * setUp(), so they cannot reach the fixture ids.
     */
    private function buildScenario(string $scenario): int
    {
        return match ($scenario) {
            // 1. Guard at line 1022: a concept with nothing authored on it.
            'no_nodes_defined' => $this->makeConcept('Concept With No Nodes'),

            // 2. Guard at line 1035: no learner state anywhere on the concept.
            'diagnostic_entry' => $this->conceptId,

            // 3. Guard at line 1044 via prerequisiteGate().
            'prerequisite_remediation' => $this->seedWeakPrerequisite(),

            // 4. stalePrerequisiteProbe() at line 1481.
            'prerequisite_stale_probe' => $this->seedStalePrerequisite(),

            // 5. The D3 hoist at line 1079 — see the provider's note.
            'misconception_outranks_retrieval' => $this->seedFlaggedBehindDueRetrieval(),

            // 6. Guard at line 1103, both of retrievalDueAction()'s returns.
            'retrieval_due' => $this->seedRetrievalDue(),
            'retrieval_content_unavailable' => $this->seedRetrievalDueWithNoItem(),

            // 7. Guard at line 1116.
            'stale_mastery' => $this->seedStaleMastery(),

            // 8. Guard at line 1141 — skip the saturated node, serve the sibling.
            'settled_skip_serves_sibling' => $this->seedSaturatedWithUntaughtSibling(),

            // 9-12. phaseFor() at line 1444, one per arm, plus the repeat pass.
            'phase_teach' => $this->seedPhaseTeach(),
            'phase_practice' => $this->seedPhasePractice(),
            'phase_check' => $this->seedPhaseCheck(),
            'phase_reteach' => $this->seedPhaseReteach(),

            // 12. The hold-back at lines 1159-1163, and its terminal half at 1171.
            'content_unavailable_holdback' => $this->seedUnservableFirstNode(),
            'content_unavailable_terminal' => $this->seedAllUnservable(),

            // 13-14. Fall-through to masteryVerdict() at line 1177, twice over:
            //        once confirming mastery already held, once GRANTING it.
            'mastery_verdict' => $this->seedAllMastered(),
            'mastery_transition' => $this->seedAboutToMaster(),

            default => throw new \InvalidArgumentException("Unknown parity scenario '{$scenario}'."),
        };
    }

    private function seedWeakPrerequisite(): int
    {
        [, $prereqK, $prereqA] = $this->makePrerequisiteOfMainConcept();
        $this->setMastery($prereqK, 0.1);
        $this->setMastery($prereqA, 0.1);

        return $this->conceptId;
    }

    /**
     * A prerequisite that passes on accuracy but whose evidence has gone cold.
     *
     * THE PREREQUISITE DELIBERATELY HAS EXACTLY ONE NODE, and that is
     * load-bearing rather than tidiness. prerequisiteProbeItem() (line 1649)
     * SHUFFLES the prerequisite's nodes before looking for an item:
     *
     *     foreach ($this->nodesForConcept(...)->shuffle() as $node)
     *
     * Over two nodes that is genuinely random, and it moves BOTH halves of
     * what this test compares: the probe reports whichever node it landed on,
     * so `node_id` and `item` differ between the two runs, and the number of
     * pal_question_metadata lookups differs too when the nodes are unevenly
     * stocked. Measured across four runs it failed 2, 2, 1 and 0 times.
     *
     * Stocking every node fixes the query count but not the payload — only
     * removing the choice fixes both. A single-node prerequisite exercises
     * this branch exactly as well, because the branch is about STALENESS, not
     * about which node gets probed.
     *
     * Anyone adding a fixture that reaches this path needs the same rule: one
     * node, or the resolved payload is a coin toss.
     */
    private function seedStalePrerequisite(): int
    {
        $prereqConceptId = $this->makeConcept('Stale Single-Node Prerequisite');

        $prereqNode = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $prereqConceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Sole prerequisite node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pal_concept_relations')->insert([
            'from_concept_id' => $this->conceptId,
            'to_concept_id' => $prereqConceptId,
            'relation_type' => 'requires',
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->makeServableQuestion($prereqNode);

        // The main concept is already diagnosed, so D1 entry does not fire
        // ahead of the D2 gate this scenario is about.
        $this->setMastery($this->kNodeId, 0.3);
        $this->setMastery($this->aNodeId, 0.3);

        // Comfortably above PREREQUISITE_THRESHOLD, so the gate passes and the
        // resolve falls through to the staleness probe rather than to
        // remediation.
        $this->setMastery($prereqNode, 0.95);

        // prerequisiteEvidenceLastSeen() reads learner_node_state.last_seen_at
        // (confirmed against the query log), so that is what has to go cold.
        // The response rows are aged too, so the fixture is not telling two
        // different stories about when this learner last worked.
        $stale = now()->subDays(EsoPolicyService::PREREQUISITE_STALE_AFTER_DAYS + 5);

        $state = $this->stateOf($prereqNode);
        $state->last_seen_at = $stale;
        $state->save();

        DB::table('eso_response_log')
            ->where('student_id', $this->studentId)
            ->where('node_id', $prereqNode)
            ->update(['created_at' => $stale]);

        return $this->conceptId;
    }

    /**
     * The regression the D3 hoist closed.
     *
     * The flagged node is the A node (sort_order 2) and the node with a due
     * retrieval is the K node (sort_order 1). Before the hoist, the node loop
     * reached K first, returned `retrieval_due`, and the misconception was
     * never seen — testing retention while a confirmed error stood
     * uncorrected. Order matters here and the fixture depends on it.
     */
    private function seedFlaggedBehindDueRetrieval(): int
    {
        $this->setRetrievalDue($this->kNodeId);
        $this->flagMisconception($this->aNodeId);

        return $this->conceptId;
    }

    private function seedRetrievalDue(): int
    {
        $this->setRetrievalDue($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        return $this->conceptId;
    }

    private function seedStaleMastery(): int
    {
        $this->setMastery($this->kNodeId, 0.95, LearnerNodeState::STATUS_MASTERED);
        $this->setMastery($this->aNodeId, 0.95, LearnerNodeState::STATUS_MASTERED);
        $this->makeServableQuestion($this->kNodeId);

        // Mastered, but nothing scheduled and the evidence is outside the
        // recency window — the branch that asks for re-verification rather
        // than silence.
        $stale = now()->subDays(EsoPolicyService::EVIDENCE_RECENCY_DAYS + 5);

        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            $state = $this->stateOf($nodeId);
            $state->last_seen_at = $stale;
            $state->next_review_at = null;
            $state->save();
        }

        DB::table('eso_response_log')
            ->where('student_id', $this->studentId)
            ->update(['created_at' => $stale]);

        return $this->conceptId;
    }

    private function seedSaturatedWithUntaughtSibling(): int
    {
        // K is at threshold WITH the evidence floor met and its check settled,
        // so the skip at line 1141 fires. A is untaught, so the loop must go
        // on and serve it rather than stopping at the skipped node.
        $this->setMastery($this->kNodeId, 1.0);
        $this->setUntaught($this->aNodeId);
        $this->makeServableQuestion($this->aNodeId);

        return $this->conceptId;
    }

    private function seedPhaseTeach(): int
    {
        $this->setUntaught($this->kNodeId);
        $this->setMastery($this->aNodeId, 0.2);
        $this->makeServableQuestion($this->kNodeId);

        return $this->conceptId;
    }

    /**
     * Taught, but short of the evidence floor.
     *
     * CLOCK SKEW MATTERS HERE. practiceComplete() (1306) compares
     * eso_response_log.created_at against learner_node_state.taught_at, and
     * the MariaDB host clock runs ~2.5h behind PHP (ResponseLog.php:20-32).
     * Both sides are stamped from PHP with explicit, well-separated offsets —
     * never bare now() on both — or the gte comparison becomes a coin flip on
     * sub-second ordering.
     */
    private function seedPhasePractice(): int
    {
        $this->setMastery($this->aNodeId, 0.2);

        foreach (range(1, 5) as $ignored) {
            $this->makeServableQuestion($this->kNodeId);
        }

        $state = $this->setUntaught($this->kNodeId);
        $state->taught_at = now()->subMinutes(10);
        $state->save();

        // One event: taught, started practising, nowhere near MIN_EVENTS_K.
        $this->seedEvidence($this->kNodeId, events: 1, at: now()->subMinutes(5));

        return $this->conceptId;
    }

    private function seedPhaseCheck(): int
    {
        $this->setMastery($this->aNodeId, 0.2);
        $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId);
        $state->taught_at = now()->subMinutes(10);
        $state->save();

        // Practice complete (the floor is met) but the check has never run:
        // cfu_passed_at is null and cfu_attempts is below CFU_MAX_CYCLES.
        $this->seedEvidence($this->kNodeId, events: EsoPolicyService::MIN_EVENTS_K, at: now()->subMinutes(5));

        return $this->conceptId;
    }

    /**
     * The repeat pass: a check was failed, so Learn reopened.
     *
     * recordCheckUnderstanding() (line ~2320) increments `cfu_attempts` and,
     * while it is still under CFU_MAX_CYCLES, nulls `taught_at`. That pair is
     * the whole signal: phaseFor() sees taught_at === null and routes to teach,
     * and teachAction() reads `cfu_attempts > 0` as $retry and emits 'reteach'
     * instead of 'teach', walking the content model's re-route ladder so the
     * second explanation can be a different FORMAT rather than the same words.
     *
     * Seeded directly rather than by failing a real check, because the parity
     * harness needs the state to be identical at the start of BOTH runs — and
     * driving a real failure first would put the write inside the measured
     * resolve. The conformance suite drives the real thing end to end.
     *
     * The estimate stays low on purpose: at threshold the node would be skipped
     * at line 1141 and never reach the phase machine at all.
     */
    private function seedPhaseReteach(): int
    {
        $this->setMastery($this->aNodeId, 0.2);
        $this->makeCfuQuestion($this->kNodeId);

        $state = $this->setUntaught($this->kNodeId, 0.3);
        $state->taught_at = null;                    // Learn reopened
        $state->cfu_passed_at = null;
        $state->cfu_attempts = 1;                    // ... by a FAILED check
        $state->save();

        return $this->conceptId;
    }

    /**
     * A retrieval falls due on a node nothing can be served for.
     *
     * retrievalDueAction() has TWO returns and this is the other one (line
     * 2814): mastery is explicitly NOT revoked and the schedule is NOT
     * advanced, because the gap is ours and not the learner's. Distinct from
     * the practice-path content_unavailable — that one is held back behind a
     * servable sibling, this one returns immediately from inside the node loop.
     *
     * The K node gets no pal_question_metadata at all, so retrievalItems()
     * comes back empty. Note it shuffles its candidates; over an empty
     * candidate set that is deterministic, which is why this fixture is safe
     * where seedStalePrerequisite() was not.
     */
    private function seedRetrievalDueWithNoItem(): int
    {
        // Mastered and overdue, with nothing authored to review.
        $this->setMastery($this->kNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        $state = $this->stateOf($this->kNodeId);
        $state->next_review_at = now()->subMinutes(5);
        $state->retention_stage = 0;
        $state->save();

        // The sibling is mastered with nothing due, so it cannot preempt.
        $this->setMastery($this->aNodeId, 0.9, LearnerNodeState::STATUS_MASTERED);

        return $this->conceptId;
    }

    /**
     * A node that wants to serve but has no answerable item, ahead of one that
     * does. The first must be held back rather than starving the sibling —
     * the S-node case the hold-back at 1159-1163 exists for.
     *
     * The node has to be PAST teaching for this to bite. teachAction() carries
     * no scored question by design (`expects: acknowledge`), so an untaught
     * node resolves happily with zero question stock and never reaches the
     * branch that can report content_unavailable. K is therefore taught and
     * short of the floor — which routes it to practice, which is what actually
     * asks for an item it does not have.
     */
    private function seedUnservableFirstNode(): int
    {
        $state = $this->setUntaught($this->kNodeId);   // no question authored
        $state->taught_at = now()->subMinutes(10);
        $state->save();

        $this->setUntaught($this->aNodeId);
        $this->makeServableQuestion($this->aNodeId);   // only the sibling is servable

        return $this->conceptId;
    }

    /**
     * Nothing in the concept can be served.
     *
     * The terminal half of the hold-back at lines 1171-1173: when no sibling
     * rescues the resolve, the learner gets an honest content_unavailable
     * rather than a mastery verdict they were never given the means to earn.
     */
    private function seedAllUnservable(): int
    {
        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            $state = $this->setUntaught($nodeId);
            $state->taught_at = now()->subMinutes(10);
            $state->save();
        }

        return $this->conceptId;
    }

    private function seedAllMastered(): int
    {
        $this->setMastery($this->kNodeId, 1.0, LearnerNodeState::STATUS_MASTERED);
        $this->setMastery($this->aNodeId, 0.95, LearnerNodeState::STATUS_MASTERED);

        return $this->conceptId;
    }

    /**
     * Mastery GRANTED on this resolve, rather than confirmed from seeded state.
     *
     * seedAllMastered() hands masteryVerdict() nodes that are already
     * STATUS_MASTERED, so the verdict confirms and writes nothing. That left
     * the whole write half of D4 uncovered — the sweep to STATUS_MASTERED and
     * the scheduleRetention() call that puts a node on the ladder. Verified by
     * mutation: perturbing scheduleRetention() went undetected until this
     * scenario existed.
     *
     * Every node here is at or above its threshold with the evidence floor met
     * and its check settled, but still STATUS_LEARNING — so the node loop skips
     * each one at line 1141 and the resolve falls through to a verdict that has
     * real work to do.
     */
    private function seedAboutToMaster(): int
    {
        // setMastery() defaults to STATUS_LEARNING and seeds the evidence the
        // floor needs, plus taught_at/cfu_passed_at so the check is settled.
        $this->setMastery($this->kNodeId, 1.0);
        $this->setMastery($this->aNodeId, 0.95);

        return $this->conceptId;
    }

    // ── Fixture helpers (conventions copied from EsoPolicyServiceTest) ────

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

    /**
     * Valid evidence events, with an EXPLICIT created_at.
     *
     * Diverges from EsoPolicyServiceTest::seedEvidence(), which passes bare
     * now(). Parity scenarios turn on practiceComplete()'s created_at >=
     * taught_at comparison, so the offset has to be stated rather than left to
     * whichever clock wins — see seedPhasePractice().
     */
    private function seedEvidence(
        int $nodeId,
        int $events = EsoPolicyService::MIN_EVENTS_K,
        string $mode = LearnerNodeState::MODE_INDEPENDENT,
        bool $hintUsed = false,
        ?\Illuminate\Support\Carbon $at = null,
    ): void {
        $at ??= now();

        for ($i = 0; $i < $events; $i++) {
            DB::table('eso_response_log')->insert([
                'student_id' => $this->studentId,
                'concept_id' => $this->conceptId,
                'node_id' => $nodeId,
                'sub_institute_id' => $this->subInstituteId,
                // Distinct per row: one event per distinct question is the rule.
                'question_id' => (900000 + $nodeId * 100 + $i),
                'correct' => true,
                'hint_used' => $hintUsed,
                'mode' => $mode,
                'created_at' => $at,
            ]);
        }
    }

    private function setMastery(int $nodeId, float $mastery, string $status = LearnerNodeState::STATUS_LEARNING): LearnerNodeState
    {
        if ($mastery >= self::EVIDENCE_SEED_FROM) {
            $this->seedEvidence($nodeId);
        }

        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $mastery,
                'attempts' => 1,
                'status' => $status,
                'last_seen_at' => now(),
                'taught_at' => now(),
                'cfu_passed_at' => now(),
            ]
        );
    }

    private function setUntaught(int $nodeId, float $mastery = 0.2): LearnerNodeState
    {
        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $mastery,
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

    private function stateOf(int $nodeId): LearnerNodeState
    {
        return LearnerNodeState::forStudent($this->studentId)
            ->where('node_id', $nodeId)
            ->firstOrFail();
    }

    /** Flag a node as holding an active misconception. */
    private function flagMisconception(int $nodeId, int $misconceptionId = 4242): LearnerNodeState
    {
        $state = $this->setMastery($nodeId, 0.9);
        $state->status = LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED;
        $state->active_misconception_id = $misconceptionId;
        $state->save();

        return $state;
    }

    /** A real servable MCQ (question + options + approved node tagging). */
    private function makeServableQuestion(int $nodeId): int
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1, // MCQ — hydrateQuestion() serves these only
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'ESO parity question',
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

    /** @return array{0:int,1:int,2:int} [questionId, correctAnswerId, wrongAnswerId] */
    private function makeCfuQuestion(int $nodeId, ?int $misconceptionId = null): array
    {
        $questionId = $this->makeServableQuestion($nodeId);

        $correct = (int) DB::table('answer_master')
            ->where('question_id', $questionId)->where('correct_answer', 1)->value('id');

        $wrong = (int) DB::table('answer_master')
            ->where('question_id', $questionId)->where('correct_answer', 0)->value('id');

        if ($misconceptionId !== null) {
            DB::table('answer_master')->where('id', $wrong)
                ->update(['misconception_id' => $misconceptionId]);
        }

        return [$questionId, $correct, $wrong];
    }

    /** @return array{0:int,1:int,2:int} [prerequisiteConceptId, prerequisiteKNodeId, prerequisiteANodeId] */
    private function makePrerequisiteOfMainConcept(bool $diagnoseMainConcept = true): array
    {
        $prereqConceptId = $this->makeConcept('Parity Prerequisite Concept');
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
        if ($diagnoseMainConcept) {
            $this->setMastery($this->kNodeId, 0.3);
            $this->setMastery($this->aNodeId, 0.3);
        }

        return [$prereqConceptId, $prereqK, $prereqA];
    }

    private function setRetrievalDue(int $nodeId, int $daysAgo = 7, int $stage = 0): LearnerNodeState
    {
        // A due review needs something to review with, or the engine correctly
        // resolves content_unavailable instead.
        $this->makeServableQuestion($nodeId);

        return LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => 0.9,
                'attempts' => 4,
                'consecutive_correct' => 2,
                'status' => LearnerNodeState::STATUS_MASTERED,
                'retention_stage' => $stage,
                'last_seen_at' => now()->subDays($daysAgo),
                'next_review_at' => now()->subMinutes(5),
                'taught_at' => now()->subDays($daysAgo),
                'cfu_passed_at' => now()->subDays($daysAgo),
            ]
        );
    }
}
