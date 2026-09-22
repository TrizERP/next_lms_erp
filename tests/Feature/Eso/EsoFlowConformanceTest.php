<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Services\Eso\EsoPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Does a learner who does everything right actually reach mastery?
 *
 * ---------------------------------------------------------------------------
 * THE GAP THIS FILLS
 * ---------------------------------------------------------------------------
 * EsoPolicyServiceTest has 146 tests and not one of them drives nextAction()
 * from a cold start through to a mastery verdict. Checked: 'diagnostic' is
 * asserted as an action in exactly two places (lines 2777 and 3778) and
 * neither continues; every test that reaches 'mastered_stop_practice' gets
 * there from directly seeded state via setMastery()/setEstimatesOnly() or
 * from a bare recordAttempt() loop, never through teach and never through the
 * check. tests/Feature/Pal/PalStudentJourneyEndToEndTest.php IS a journey
 * test, but of the PAL V4 loop — it goes through LearnerStateEngine and
 * pal_concepts and never touches this engine.
 *
 * So the suite pins every rule individually and nothing pins them COMPOSED.
 * A change that leaves all 146 green can still leave a learner stranded — on
 * a phase that never settles, on a node that starves its siblings, or short
 * of a floor no remaining action can fill. That is what this asserts.
 *
 * ---------------------------------------------------------------------------
 * WHY IT MATTERS MORE AFTER THE FLOW REFACTOR THAN BEFORE
 * ---------------------------------------------------------------------------
 * Once a school's flow is configurable, "the journey still terminates" stops
 * being a property of one hardcoded cascade and becomes a property that every
 * shipped profile has to prove separately. Disabling the check is the clearest
 * case: checkSettled() (EsoPolicyService.php:1423) returns false while a CFU
 * question exists and has not been passed, the skip at 1141-1146 then never
 * fires, phaseFor() keeps answering 'check', and nothing serves it — an
 * infinite loop built entirely out of correct-looking parts.
 *
 * ---------------------------------------------------------------------------
 * THE TWO JOURNEYS
 * ---------------------------------------------------------------------------
 * There are two, and both are legitimate:
 *
 *   NEEDS TEACHING  — fails the entry diagnostic, then does everything right.
 *                     Teach -> Practice -> Check -> Mastery, with the evidence
 *                     floor actually met. This is the journey the product
 *                     describes and the one most of this file is about.
 *
 *   CLEAN SWEEP     — aces the entry diagnostic and is finished in two steps,
 *                     mastered without ever practising. Deliberate: see
 *                     test_a_clean_sweep_of_the_diagnostic_short_circuits_the_whole_journey.
 *
 * Which one you get turns entirely on whether the diagnostic is answered
 * correctly, which is why answerDiagnostic() takes that as a parameter rather
 * than always doing the obvious thing.
 *
 * At step 1 this runs against the current engine. At step 5 the cases become a
 * data provider over the four shipped profiles, and a cross-profile assertion
 * (identical mastery requirements for all of them) becomes the executable form
 * of "the mastery verdict is the same for every school".
 */
class EsoFlowConformanceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * About three times the longest legitimate journey.
     *
     * Two nodes x MIN_EVENTS_K practice events, plus a teach, plus a check per
     * node, is roughly 12 steps. The cap is not a tuning knob — it is what
     * turns "this loops forever" from a hung test suite into a named failure
     * with a transcript attached.
     */
    private const MAX_STEPS = 60;

    /**
     * Questions authored per node.
     *
     * Deliberately more than the floor needs. practicePoolSize() caps the
     * practice target at the stock that actually exists (lines 1318-1338), so
     * a thin pool would let a node finish practice on fewer events than
     * MIN_EVENTS_K and this test would then be measuring the cap rather than
     * the floor.
     *
     * Eight rather than six because a FAILED check restarts practice: reteach
     * re-stamps taught_at, and practiceComplete() counts only evidence newer
     * than it, so the reteach journey below practises each node twice over.
     */
    private const QUESTIONS_PER_NODE = 8;

    private EsoPolicyService $policy;

    private int $subInstituteId;

    private int $studentId;

    private int $subjectId;

    private int $standardId;

    private int $chapterId;

    private int $conceptId;

    private int $kNodeId;

    private int $aNodeId;

    /** @var array<int, string> */
    private array $transcript = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(EsoPolicyService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'ESO Conformance School',
            'ShortCode' => 'ESOC' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'eso-conformance@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'eso-conformance@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Journey',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'ESO Conformance Subject ' . random_int(1000, 9999),
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
            'chapter_name' => 'ESO Conformance Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'ESO Conformance Concept',
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'chapter_id' => $this->chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        [$this->kNodeId, $this->aNodeId] = $this->makeKANodes($this->conceptId);

        foreach ([$this->kNodeId, $this->aNodeId] as $nodeId) {
            for ($i = 0; $i < self::QUESTIONS_PER_NODE; $i++) {
                $this->makeServableQuestion($nodeId);
            }
        }
    }

    // ── The journey ──────────────────────────────────────────────────────

    public function test_a_learner_who_needs_teaching_reaches_an_earned_mastery_verdict(): void
    {
        $verdict = $this->driveJourney();

        // 1. TERMINATES. driveJourney() fails with the transcript attached if
        //    it does not, so reaching here is the assertion.
        $this->assertNotNull($verdict, 'The journey never produced a mastery verdict.');

        // 2. MASTERY WAS EARNED, NOT GRANTED. A flow that reaches
        //    'mastered_stop_practice' by being permissive is a regression, not
        //    a pass — so the verdict is checked against the evidence floor
        //    rather than against its own headline boolean.
        $this->assertTrue($verdict['mastered'], "The verdict is not mastery.\n" . $this->renderTranscript());

        $this->assertTrue(
            $verdict['evidence']['knowledge']['meets_floor'],
            'Knowledge mastery was reported without meeting the evidence floor.'
        );
        $this->assertTrue(
            $verdict['evidence']['application']['meets_floor'],
            'Application mastery was reported without meeting the evidence floor.'
        );
        $this->assertSame(
            0,
            $verdict['evidence']['remaining_events'],
            'Mastery was reported while demonstrations were still outstanding.'
        );
        $this->assertFalse(
            $verdict['evidence']['misconception_blocks'],
            'A clean journey must not end with a misconception still flagged.'
        );

        // 3. NO PHASE STARVES. Every phase the flow declares has to actually
        //    be reached. A journey that never teaches, or never checks, is not
        //    the flow the product describes even when it ends in mastery.
        //
        //    Asserted on observed ACTIONS here because there is no stage
        //    registry yet. At step 5 this becomes
        //    $plan->enabledStageKeys() minus the conditional ones, read off
        //    isConditional() on the handler, so a new stage cannot silently
        //    opt out of the check.
        foreach (['teach', 'practice', 'check_understanding'] as $expected) {
            $this->assertContains(
                $expected,
                $this->actionsSeen(),
                "The journey never reached '{$expected}'.\n" . $this->renderTranscript()
            );
        }

        // 4. NOTHING LOOPS. The bound is the most any single node can
        //    legitimately need: CFU_MAX_CYCLES passes, each costing a full
        //    practice run plus its check.
        $bound = EsoPolicyService::CFU_MAX_CYCLES
            * (EsoPolicyService::MIN_EVENTS_K + EsoPolicyService::CFU_ITEM_COUNT);

        // Polled steps only. A pushed action is the SAME resolve cycle's
        // response, not a new one, so counting both would double every step
        // and make the bound meaningless.
        $polled = array_filter($this->transcript, fn (string $s) => ! str_contains($s, '(pushed)'));

        foreach (array_count_values($polled) as $step => $count) {
            $this->assertLessThanOrEqual(
                $bound,
                $count,
                "'{$step}' repeated {$count} times, over the bound of {$bound}.\n" . $this->renderTranscript()
            );
        }
    }

    /**
     * Both gated node types are measured, and neither is quietly skipped.
     *
     * masteryVerdict() reports a type with no authored node as not-applicable
     * rather than satisfied (the comment at lines 2576-2580 names three live
     * concepts in chapter 1014 with no A node). This concept authors both, so
     * both must come back applicable — otherwise the journey above could pass
     * having only ever demonstrated knowledge.
     */
    public function test_both_gated_node_types_are_actually_assessed(): void
    {
        $verdict = $this->driveJourney();

        $this->assertTrue($verdict['evidence']['knowledge']['applicable']);
        $this->assertTrue($verdict['evidence']['application']['applicable']);

        $this->assertFalse($verdict['evidence']['knowledge']['not_assessed'], 'K nodes were never measured.');
        $this->assertFalse($verdict['evidence']['application']['not_assessed'], 'A nodes were never measured.');

        $this->assertNotNull($verdict['knowledge_mastery']);
        $this->assertNotNull($verdict['application_mastery']);
    }

    /**
     * The OTHER legitimate journey: a learner who already knows it.
     *
     * A clean sweep of the entry diagnostic — every item correct, on at least
     * MIN_EVENTS_K distinct items — sets STATUS_MASTERED at
     * EsoPolicyService.php:918 and the learner is finished in two steps,
     * having never been taught, never practised and never checked.
     *
     * The uncomfortable part is asserted rather than avoided: this mastery
     * does NOT meet the evidence floor. That is deliberate, and the comment at
     * lines 898-904 gives the reason — without the short-circuit the engine
     * deadlocks outright, because hasSatisfiedOwnThreshold() skips the
     * saturated node while masteryVerdict() withholds mastery for want of
     * non-diagnostic evidence, leaving the learner told to "continue
     * practising" with no node to practise on, forever.
     *
     * Pinned here so that (a) nobody later "fixes" the floor gap and
     * reintroduces that deadlock, and (b) if a flow profile ever DOES change
     * this, the change is visible rather than silent. It is also why the
     * journey above has to answer the diagnostic wrong: with a clean sweep
     * there is no journey left to conform.
     */
    public function test_a_clean_sweep_of_the_diagnostic_short_circuits_the_whole_journey(): void
    {
        $verdict = $this->driveJourney(aceDiagnostic: true);

        $this->assertTrue($verdict['mastered']);

        $this->assertSame(
            ['diagnostic', 'mastered_stop_practice'],
            $this->actionsSeen(),
            "A clean sweep must skip teaching, practice and the check outright.
" . $this->renderTranscript()
        );

        // Granted, not earned — and the engine says so honestly in the payload
        // rather than reporting a floor it did not clear.
        $this->assertFalse(
            $verdict['evidence']['knowledge']['meets_floor'],
            'A clean sweep records no valid evidence events, so the floor cannot be met. '
            . 'If this now passes, the deadlock described at EsoPolicyService.php:898-904 is back.'
        );
        $this->assertGreaterThan(0, $verdict['evidence']['remaining_events']);

        // The node still enters the retention ladder, so the claim gets
        // re-tested later rather than standing unexamined forever.
        $this->assertNotNull(
            $this->stateOf($this->kNodeId)->next_review_at,
            'Mastery granted by clean sweep must still be scheduled for retrieval.'
        );
    }

    /**
     * A learner who gets the check wrong is retaught, and still gets there.
     *
     * This is the only path that reaches `reteach`, and it was uncovered:
     * every other journey answers correctly, so `cfu_attempts` stays 0 and
     * teachAction()'s $retry branch (line ~1991) never runs.
     *
     * What it protects is the shape of the recovery, not just its existence.
     * recordCheckUnderstanding() nulls `taught_at` on a failure, which reopens
     * LEARN rather than sending the learner back round practice — so the
     * engine re-explains before it re-tests. If a refactor routed a failed
     * check to practice instead, every other test would stay green and the
     * product behaviour would be materially different.
     *
     * Mastery must still be EARNED at the end. A failed check costs a lap; it
     * must not cost the standard, and it must not waive it either.
     */
    public function test_a_failed_check_reopens_learn_and_still_reaches_earned_mastery(): void
    {
        $verdict = $this->driveJourney(failFirstCheck: true);

        $this->assertContains(
            'reteach',
            $this->actionsSeen(),
            "A failed check must reopen Learn, not route back to practice.
" . $this->renderTranscript()
        );

        // The re-explanation comes BEFORE the next check — the whole point of
        // reopening Learn rather than re-serving the gate.
        $order = $this->transcriptActions();
        $firstCheck = array_search('check_understanding', $order, true);
        $reteachAt = array_search('reteach', $order, true);
        $this->assertIsInt($firstCheck);
        $this->assertIsInt($reteachAt);
        $this->assertGreaterThan(
            $firstCheck,
            $reteachAt,
            "The reteach must follow the failed check.
" . $this->renderTranscript()
        );

        // The failure is recorded where the loop guard reads it.
        $this->assertGreaterThan(
            0,
            (int) $this->stateOf($this->kNodeId)->cfu_attempts
                + (int) $this->stateOf($this->aNodeId)->cfu_attempts,
            'A failed check must increment cfu_attempts.'
        );

        // And the standard is unchanged by the detour.
        $this->assertTrue($verdict['mastered'], "Mastery was not reached.
" . $this->renderTranscript());
        $this->assertTrue($verdict['evidence']['knowledge']['meets_floor']);
        $this->assertTrue($verdict['evidence']['application']['meets_floor']);
        $this->assertSame(0, $verdict['evidence']['remaining_events']);
    }

    // ── The driver ───────────────────────────────────────────────────────

    /**
     * Resolve, act, repeat — answering everything correctly — until the engine
     * says the concept is mastered.
     *
     * Every action the engine can return is handled explicitly. An unhandled
     * one fails loudly rather than being ignored: silently skipping an action
     * would let the loop spin to MAX_STEPS and report "did not terminate",
     * which points at the wrong thing.
     *
     * @return array<string,mixed> the terminal mastery verdict
     */
    private function driveJourney(bool $aceDiagnostic = false, bool $failFirstCheck = false): array
    {
        $checksFailed = 0;
        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $action = $this->policy->nextAction($this->studentId, $this->conceptId, $this->subInstituteId);
            $name = (string) ($action['action'] ?? '?');
            $nodeId = $action['node_id'] ?? null;

            $this->transcript[] = $nodeId === null ? $name : "{$name}#{$nodeId}";

            switch ($name) {
                case 'mastered_stop_practice':
                    return $action;

                case 'diagnostic':
                    $this->answerDiagnostic(correctly: $aceDiagnostic);
                    break;

                // Teaching carries no scored question by design — the learner
                // acknowledges and the next resolve moves on. taught_at is
                // stamped by teachAction(), so this is progress, not a no-op.
                case 'teach':
                case 'reteach':
                    break;

                case 'practice':
                    $this->recordPushed($this->answerPractice((int) $nodeId));
                    break;

                case 'check_understanding':
                    // Fail exactly one check, the first. Failing every check
                    // would spend CFU_MAX_CYCLES and exercise the loop guard
                    // instead of the re-route ladder this drives.
                    $failThis = $failFirstCheck && $checksFailed === 0;
                    $checksFailed += $failThis ? 1 : 0;
                    $this->recordPushed($this->answerCheck((int) $nodeId, correctly: ! $failThis));
                    break;

                case 'retrieval_due':
                    $this->recordPushed($this->answerRetrieval((int) $nodeId));
                    break;

                default:
                    $this->fail(
                        "A learner answering everything correctly should never reach '{$name}'.\n"
                        . $this->renderTranscript()
                    );
            }
        }

        $this->fail(
            'The journey did not terminate within ' . self::MAX_STEPS . " steps.\n" . $this->renderTranscript()
        );
    }

    /**
     * Sit the entry diagnostic.
     *
     * `$correctly` picks which of the TWO legitimate journeys this is, and the
     * difference is not cosmetic. scoreDiagnostic() computes
     * (EsoPolicyService.php:905):
     *
     *     $cleanSweep = $skip && $wrongAnswers === 0
     *                   && count(array_unique($askedQuestionIds)) >= MIN_EVENTS_K
     *
     * and a clean sweep sets STATUS_MASTERED outright. So a learner who aces
     * the diagnostic NEVER sees teach, practice or the check — they are
     * finished in two steps. Answering wrong is therefore the only way to
     * drive the journey this suite mainly exists to cover.
     *
     * The wrong answers carry no misconception_id (makeServableQuestion()
     * leaves it null), so this produces a learner who needs teaching, not one
     * who trips D3 into a contrast pair.
     */
    private function answerDiagnostic(bool $correctly): void
    {
        $items = $this->policy->diagnosticItems($this->conceptId, $this->subInstituteId);

        $this->assertNotEmpty($items, 'The engine asked for a diagnostic it cannot serve.');

        $responses = [];

        foreach ($items as $item) {
            $questionId = (int) $item['question_id'];

            $responses[] = [
                'node_id' => (int) $item['node_id'],
                'answer_master_id' => $correctly
                    ? $this->correctAnswerFor($questionId)
                    : $this->wrongAnswerFor($questionId),
            ];
        }

        $this->policy->scoreDiagnostic($this->studentId, $this->conceptId, $this->subInstituteId, $responses);
    }

    private function answerPractice(int $nodeId): array
    {
        $item = $this->policy->practiceItem($nodeId, $this->subInstituteId, $this->stateOf($nodeId));

        $this->assertNotNull($item, "The engine asked for practice on node {$nodeId} but can serve no item.");

        return $this->policy->recordAttempt($this->studentId, $nodeId, $this->conceptId, $this->subInstituteId, [
            'answer_master_id' => $this->correctAnswerFor((int) $item['question_id']),
            // Independent and hint-free: MIN_INDEPENDENT is part of the floor,
            // so a journey answered entirely in guided mode would never reach
            // mastery however many questions it got right.
            'mode' => LearnerNodeState::MODE_INDEPENDENT,
        ]);
    }

    private function answerCheck(int $nodeId, bool $correctly = true): array
    {
        $items = $this->policy->checkUnderstandingItems($nodeId, $this->subInstituteId, $this->stateOf($nodeId));

        $this->assertNotEmpty($items, "The engine asked for a check on node {$nodeId} but can serve no item.");

        $responses = array_map(
            fn (array $item) => [
                'answer_master_id' => $correctly
                    ? $this->correctAnswerFor((int) $item['question_id'])
                    : $this->wrongAnswerFor((int) $item['question_id']),
            ],
            $items
        );

        return $this->policy->recordCheckUnderstanding($this->studentId, $nodeId, $this->conceptId, $this->subInstituteId, $responses);
    }

    private function answerRetrieval(int $nodeId): array
    {
        $items = $this->policy->retrievalItems($nodeId, $this->subInstituteId);

        $this->assertNotEmpty($items, "The engine asked for a retrieval check on node {$nodeId} but can serve no item.");

        $responses = array_map(
            fn (array $item) => ['answer_master_id' => $this->correctAnswerFor((int) $item['question_id'])],
            $items
        );

        return $this->policy->retrievalCheck($this->studentId, $nodeId, $this->conceptId, $this->subInstituteId, $responses);
    }

    /**
     * Record an action the engine PUSHED rather than one we polled for.
     *
     * The write methods do not merely record and fall silent — several of them
     * resolve the learner's next action inline and hand it back as the response
     * to the submission. recordCheckUnderstanding() is the one that matters
     * here: on a failed check it nulls `taught_at`, then resolves and returns
     * `reteach` itself, which re-stamps `taught_at` on the way out.
     *
     * A driver that ignores the return value and simply re-polls nextAction()
     * therefore never observes the reteach at all — by the time it asks, the
     * learner has legitimately moved on to practice against the new cutoff.
     * Verified directly: recordCheckUnderstanding() returned
     * `action=reteach` while the very next nextAction() returned `practice`.
     *
     * So the transcript records both what we asked for and what we were handed,
     * because both are things the learner actually saw. This also matters for
     * the stage extraction: these inline resolutions live inside the WRITE
     * paths, which are explicitly out of scope for the refactor and must keep
     * resolving exactly as they do now.
     *
     * @param  array<string,mixed>  $response
     */
    private function recordPushed(array $response): void
    {
        $name = $response['action'] ?? null;

        if (! is_string($name) || $name === '') {
            return;
        }

        $nodeId = $response['node_id'] ?? null;
        $this->transcript[] = $nodeId === null ? "{$name} (pushed)" : "{$name}#{$nodeId} (pushed)";
    }

    // ── Reporting ────────────────────────────────────────────────────────

    /**
     * The journey as a numbered list.
     *
     * Attached to every failure message in this file. A bare "did not
     * terminate" says nothing; the transcript shows exactly which node and
     * which phase the learner got stuck on, which is the whole diagnosis.
     */
    private function renderTranscript(): string
    {
        $lines = ['Journey transcript:'];

        foreach ($this->transcript as $i => $step) {
            $lines[] = sprintf('  %2d. %s', $i + 1, $step);
        }

        return implode("\n", $lines);
    }

    /** @return array<int, string> every action in order, node suffix stripped */
    private function transcriptActions(): array
    {
        return array_map(
            fn (string $step) => explode('#', str_replace(' (pushed)', '', $step))[0],
            $this->transcript
        );
    }

    /** @return array<int, string> distinct action names, node suffix stripped */
    private function actionsSeen(): array
    {
        return array_values(array_unique($this->transcriptActions()));
    }

    // ── Fixtures (conventions copied from EsoPolicyServiceTest) ───────────

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

    private function makeServableQuestion(int $nodeId): int
    {
        $questionId = (int) DB::table('lms_question_master')->insertGetId([
            'question_type_id' => 1, // MCQ — hydrateQuestion() serves these only
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'chapter_id' => $this->chapterId,
            'question_title' => 'ESO conformance question',
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

    /** The marked-correct option, resolved server-side exactly as production does. */
    private function correctAnswerFor(int $questionId): int
    {
        return (int) DB::table('answer_master')
            ->where('question_id', $questionId)
            ->where('correct_answer', 1)
            ->value('id');
    }

    /** A marked-wrong option. Carries no misconception_id, so it cannot trip D3. */
    private function wrongAnswerFor(int $questionId): int
    {
        return (int) DB::table('answer_master')
            ->where('question_id', $questionId)
            ->where('correct_answer', 0)
            ->value('id');
    }

    private function stateOf(int $nodeId): LearnerNodeState
    {
        return LearnerNodeState::forStudent($this->studentId)
            ->where('node_id', $nodeId)
            ->firstOrFail();
    }
}
