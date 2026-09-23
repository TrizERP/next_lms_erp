<?php

namespace App\Domain\Eso\Flow;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;
use Illuminate\Support\Collection;

/**
 * The narrow surface a flow stage may call on the engine.
 *
 * ---------------------------------------------------------------------------
 * WHAT A STAGE IS, AND IS NOT
 * ---------------------------------------------------------------------------
 * A stage owns a POSITION in the resolve order and whether it runs at all. It
 * does NOT own an algorithm. PrerequisiteGateStage decides *when* the
 * prerequisite gate is consulted; prerequisiteGate() still decides what a met
 * prerequisite means, and it stays on the engine.
 *
 * That division is deliberate, and it is what a school actually buys: schools
 * ask for a different ORDER of steps, never for different arithmetic. Pulling
 * the algorithms out as well would mean promoting roughly thirty more private
 * helpers — prerequisiteGate() alone reaches conceptMasteryAverage(),
 * unmetPrerequisiteConceptIds(), prerequisiteEvidenceLastSeen(),
 * prerequisiteProbeItem() and nodesForConcept() — which would turn this
 * interface into the whole class and invert the encapsulation it exists to
 * provide. It would also move thousands of lines on the single riskiest step
 * of the rollout, for no behaviour anyone asked for.
 *
 * So: the branch entry points below are the seams. Everything behind them is
 * untouched.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS DELIBERATELY ABSENT
 * ---------------------------------------------------------------------------
 * applyUpdate(), logResponse(), scheduleRetention(), recordAttempt(),
 * recordCheckUnderstanding() and retrievalCheck(). The write paths are out of
 * scope for configurable flows: a school may change the route a learner takes,
 * never what counts as evidence. A stage that cannot reach those methods
 * cannot change the mastery rule by accident.
 *
 * ---------------------------------------------------------------------------
 * HOW EsoPolicyService SATISFIES IT
 * ---------------------------------------------------------------------------
 * The service declares `implements EsoFlowPort` and thirteen of these methods
 * are promoted from `protected` to `public`. Promotion is a widening — no body
 * moves, no call site changes, no behaviour changes. Every signature below is
 * transcribed verbatim, so a drift fails at compile time rather than at
 * runtime.
 *
 * ---------------------------------------------------------------------------
 * CONTAINER SAFETY
 * ---------------------------------------------------------------------------
 * An interface in App\Domain depending only on models and Collection. It adds
 * no edge to the resolution cycle documented at PALServiceProvider.php:94-100,
 * and nothing here is ever bound in the container.
 */
interface EsoFlowPort
{
    // ── Learner state ────────────────────────────────────────────────────

    /** One node's state, created at defaults if absent. */
    public function stateFor(int $studentId, int $nodeId, int $subInstituteId): LearnerNodeState;

    /**
     * States for a set of nodes. One query for the student's whole set,
     * cached against LearnerNodeState::writeVersion().
     *
     * @param  mixed  $nodeIds
     */
    public function statesForNodes(int $studentId, $nodeIds): Collection;

    /**
     * Valid evidence per node id, excluding diagnostic, CFU and retrieval
     * modes (ADR-001 §4.1).
     *
     * @return array<int, array{events:int, independent:int}>
     */
    public function evidenceByNode(int $studentId, Collection $nodes): array;

    /** Mastery held, but its newest evidence is outside the recency window. */
    public function isConceptStale(int $studentId, Collection $nodes, Collection $states): bool;

    // ── Node predicates, for the settled-skip decision ───────────────────

    /** Does this node's own estimate already clear D4's per-type threshold? */
    public function hasSatisfiedOwnThreshold(ConceptNode $node, LearnerNodeState $state): bool;

    /**
     * Does this node carry the distinct demonstrations ADR-001 requires?
     *
     * @param  array<int, array{events:int, independent:int}>  $evidence
     */
    public function nodeMeetsEvidenceFloor(ConceptNode $node, array $evidence): bool;

    /**
     * Is the check for this node closed — passed, loop guard spent, no check
     * item authored, or the check phase switched off for this institute?
     */
    public function checkSettled(ConceptNode $node, LearnerNodeState $state, int $subInstituteId): bool;

    // ── Branch entry points — the seams a stage owns the position of ─────

    /**
     * D2. Null means "prerequisites are fine, carry on" — the contract the
     * cascade already relies on at line 1046.
     *
     * @return array<string,mixed>|null
     */
    public function prerequisiteGate(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): ?array;

    /**
     * D3. Serve the contrast pair for a flagged misconception.
     *
     * @return array<string,mixed>
     */
    public function reserveContrastPairAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array;

    /**
     * D5. A scheduled retrieval check that has come due.
     *
     * @return array<string,mixed>
     */
    public function retrievalDueAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array;

    /**
     * Re-verify a mastered node whose evidence has gone cold.
     *
     * @return array<string,mixed>
     */
    public function staleMasteryAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array;

    /**
     * The Learn / Practice / Check machine for one node.
     *
     * @param  array<int, array{events:int, independent:int}>|null  $nodeEvidence
     * @return array<string,mixed>
     */
    public function teachOrPracticeAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, ?array $nodeEvidence = null, bool $silent = false): array;

    /**
     * D4. The terminal concept verdict.
     *
     * @return array<string,mixed>
     */
    public function masteryVerdict(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): array;

    // ── Emitters ─────────────────────────────────────────────────────────

    /**
     * Shape a resolved action.
     *
     * Key order is part of the contract: EsoEngineController serialises this
     * straight to JSON, so a stage builds its payload through here rather than
     * assembling an array of its own.
     *
     * @return array<string,mixed>
     */
    public function respond(string $action, ?int $conceptId, ?int $nodeId, string $ruleFired, ?string $llmInstruction): array;

    /**
     * Write one decision-log row.
     *
     * Stages reach this through EsoFlowContext::log(), which applies the
     * silent check.
     *
     * @param  array<string,mixed>  $stateSnapshot
     */
    public function log(int $studentId, ?int $conceptId, ?int $nodeId, int $subInstituteId, array $stateSnapshot, string $ruleFired, string $action, ?string $llmInstruction = null): DecisionLog;
}
