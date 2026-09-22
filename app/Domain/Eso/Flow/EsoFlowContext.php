<?php

namespace App\Domain\Eso\Flow;

use Illuminate\Support\Collection;

/**
 * Everything a stage may read about the resolve it is part of.
 *
 * ---------------------------------------------------------------------------
 * WHY THE EXPENSIVE FIELDS ARE LAZY
 * ---------------------------------------------------------------------------
 * The hardcoded cascade fetches in a deliberate order and RETURNS BETWEEN
 * FETCHES. A concept with no authored nodes resolves in ONE query (line 1024,
 * before states are ever loaded); a cold-start diagnostic resolves in three
 * (line 1035, before evidence or staleness are computed). Those early exits
 * are not incidental — they are the cheapest paths through the engine and the
 * most frequently taken.
 *
 * An eagerly built context would load states, evidence and staleness before
 * the first stage ran, so `no_nodes_defined` would cost four queries instead
 * of one. Every behavioural assertion would still pass — the same action comes
 * back — while the engine quietly got slower on its hottest path. That is
 * exactly the regression EsoFlowParityTest's query-count assertion exists to
 * catch, and it would catch this.
 *
 * So each expensive field is resolved on FIRST ACCESS and memoised. A stage
 * that needs evidence gets it; a stage that returns before evidence is
 * relevant never pays for it. The "resolved once per concept" property the
 * cascade relies on (lines 1061-1065) is preserved by the memo rather than by
 * discipline — a stage physically cannot re-query it.
 *
 * The engine handed in is the SAME EsoPolicyService instance nextAction() was
 * called on, so its per-request memoisation keeps exactly its current
 * lifetime. That is the decisive argument for a port over an extracted helper
 * object: a helper with a different lifetime would give two different answers
 * to practicePoolSize() inside one resolve.
 */
final class EsoFlowContext
{
    private ?Collection $states = null;

    /** @var array<int, array{events:int, independent:int}>|null */
    private ?array $evidence = null;

    private ?bool $conceptStale = null;

    /**
     * @param  Collection  $nodes  the concept's K/A/S nodes, in sort_order
     * @param  Collection|null  $states  already-loaded states, keyed by node_id
     */
    public function __construct(
        public readonly EsoFlowPort $engine,
        public readonly int $studentId,
        public readonly int $conceptId,
        public readonly int $subInstituteId,
        public readonly Collection $nodes,
        public readonly bool $silent,
        public readonly EsoFlowPlan $plan,
        ?Collection $states = null,
    ) {
        // Seeded rather than re-fetched when the caller already has them.
        //
        // nextActionPipeline() must read the learner's pinned flow version
        // BEFORE it can resolve the plan, and the pin lives on these rows.
        // Handing them straight in is what stops that read costing a second
        // query that the lazy path would then duplicate.
        $this->states = $states;
    }

    /**
     * Learner state for this concept's nodes, keyed by node_id.
     *
     * One query for the student's whole state set, cached by the engine
     * against LearnerNodeState::writeVersion() — so this is cheap after the
     * first call anywhere in the request, not just within this context.
     */
    public function states(): Collection
    {
        return $this->states ??= $this->engine
            ->statesForNodes($this->studentId, $this->nodes->pluck('id'))
            ->keyBy('node_id');
    }

    /**
     * Valid evidence per node id — diagnostic, CFU and retrieval modes
     * excluded (ADR-001 §4.1).
     *
     * @return array<int, array{events:int, independent:int}>
     */
    public function evidence(): array
    {
        return $this->evidence ??= $this->engine->evidenceByNode($this->studentId, $this->nodes);
    }

    /**
     * Mastery is held but its newest evidence is outside the recency window.
     *
     * A pure read: it never writes, so no learner is downgraded by someone
     * loading a dashboard.
     */
    public function conceptStale(): bool
    {
        return $this->conceptStale ??= $this->engine->isConceptStale(
            $this->studentId,
            $this->nodes,
            $this->states()
        );
    }

    /**
     * Record a decision, unless this is a silent resolve.
     *
     * Every stage logs through here rather than calling the engine directly.
     * The silent path is not cosmetic: chapterDashboard() reaches
     * masteryVerdict() via conceptStatusFor(), and nextEligibleConcept()
     * reaches conceptStatusFor() in turn, so a dashboard load runs the whole
     * cascade. A stage that forgot the silent check would write an audit row
     * for every dashboard render, and eso_decision_log is what the pilot
     * metrics are computed from.
     *
     * @param  array<string,mixed>  $stateSnapshot
     */
    public function log(?int $nodeId, array $stateSnapshot, string $ruleFired, string $action, ?string $llmInstruction = null): void
    {
        if ($this->silent) {
            return;
        }

        $this->engine->log(
            $this->studentId,
            $this->conceptId,
            $nodeId,
            $this->subInstituteId,
            $stateSnapshot,
            $ruleFired,
            $action,
            $llmInstruction
        );
    }
}
