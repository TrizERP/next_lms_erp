<?php

namespace App\Services\Eso;

use App\Services\PAL\Coherence\MasteryUpdater;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The seam between the Adaptive Learning Engine and the school's EXISTING
 * learning-evidence architecture.
 *
 * Until this class existed, ESO was a closed silo: it wrote learner_node_state,
 * eso_decision_log and eso_response_log, and nothing else in the estate — not
 * pal_learning_evidence, not pal_concept_mastery, not the Neo4j
 * (:StuDetail)-[:HAS_MASTERY]->(:Concept) graph — ever saw a single thing a
 * student did inside it. A student could master a concept adaptively and remain
 * invisible to the coherence map and to Career Intelligence.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS DOES NOT WRITE THE GRAPH ITSELF
 * ---------------------------------------------------------------------------
 * There is already exactly one write path to learner mastery — MasteryUpdater —
 * and it owns real invariants this class has no business re-implementing: the
 * BKT replay that makes the number reproducible, the transaction boundary, and
 * the `graph_synced_at IS NULL` outbox that makes a Neo4j outage turn evidence
 * LATE rather than LOST. Writing Cypher from here would create a second,
 * divergent source of mastery truth. So everything goes through MasteryUpdater.
 *
 * ---------------------------------------------------------------------------
 * WHY EVERY METHOD SWALLOWS ITS OWN FAILURES
 * ---------------------------------------------------------------------------
 * Same rule the gamification hand-off already follows in EsoPolicyService:
 * downstream reporting must never be able to break a learning step. A student
 * mid-practice does not lose their answer because the coherence map's BKT
 * settings are misconfigured or the graph is unreachable.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS DELIBERATELY NOT SENT
 * ---------------------------------------------------------------------------
 * Check-For-Understanding responses. The CFU gate is defined as not being
 * mastery evidence (EsoPolicyService::recordCheckUnderstanding() applies no
 * mastery update for them), and that invariant has to hold all the way to the
 * graph — otherwise a check the engine promised "doesn't count" would quietly
 * move the student's mastery everywhere else in the school.
 */
class EsoEvidenceBridge
{
    /**
     * Names this engine in `pal_learning_evidence.evidence_source`, so its
     * contribution is distinguishable from the coherence map's own
     * ('coherence_map') in every downstream report and replay.
     */
    public const SOURCE = 'eso_adaptive';

    // Outcome kinds. These are NOT 'question_response', which is exactly why
    // they never reach the BKT replay — see MasteryUpdater::recordOutcome().
    public const OUTCOME_MASTERED = 'concept_mastered';

    public const OUTCOME_RETAINED = 'retention_passed';

    public const OUTCOME_RETENTION_LAPSED = 'retention_lapsed';

    public const OUTCOME_MISCONCEPTION_DETECTED = 'misconception_detected';

    public const OUTCOME_MISCONCEPTION_CORRECTED = 'misconception_corrected';

    public function __construct(protected MasteryUpdater $mastery)
    {
    }

    /**
     * Hand one ESO operation's scored responses to the shared ledger.
     *
     * Called once per OPERATION (a submitted diagnostic, one practice attempt,
     * one retrieval check) rather than once per response — see
     * MasteryUpdater::recordBatch() for why a per-response call would make N
     * synchronous graph round-trips where one is owed.
     *
     * @param  array<int, array{question_id:?int, correct:bool, misconception_tag?:?string}>  $responses
     */
    public function recordResponses(int $studentId, int $conceptId, int $subInstituteId, array $responses): void
    {
        if ($responses === []) {
            return;
        }

        try {
            $this->mastery->recordBatch(
                $studentId,
                $conceptId,
                $subInstituteId,
                array_map(
                    fn (array $response) => $response + ['evidence_source' => self::SOURCE],
                    $responses
                )
            );
        } catch (Throwable $e) {
            // Durable in eso_response_log regardless; this is a reporting
            // hand-off, and it is never allowed to fail a student's answer.
            Log::warning('ESO evidence hand-off failed; the response is still recorded in eso_response_log', [
                'student_id' => $studentId,
                'concept_id' => $conceptId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a meaningful ESO decision outcome — a mastery verdict, a survived
     * or lapsed spaced-retrieval check, a misconception detected or corrected.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordOutcome(int $studentId, int $conceptId, string $outcome, array $context = []): void
    {
        try {
            $this->mastery->recordOutcome($studentId, $conceptId, $outcome, self::SOURCE, $context);
        } catch (Throwable $e) {
            Log::warning('ESO outcome evidence hand-off failed; the decision is still recorded in eso_decision_log', [
                'student_id' => $studentId,
                'concept_id' => $conceptId,
                'outcome' => $outcome,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
