<?php

namespace App\Domain\Eso\Flow;

/**
 * What a stage declares about ITSELF, independent of how it resolves.
 *
 * Split out from EsoFlowStage / EsoFlowNodeStage because the validator needs
 * only this half. The two pipelines run at different granularities — one per
 * concept, one per node — so their resolve() signatures differ, but their
 * metadata is identical and the composition rules apply to both equally.
 *
 * ---------------------------------------------------------------------------
 * WHY tier() IS A METHOD AND NOT A CONFIG KEY
 * ---------------------------------------------------------------------------
 * This is the single most important decision in the flow system. A LOCKED
 * stage is locked because its POSITION ENCODES CORRECTNESS, not preference.
 * The misconception scan was hoisted out of the node loop as a bug fix
 * (EsoPolicyService.php:1067-1078): while it lived inside the loop, precedence
 * depended on pal_concept_nodes.sort_order, so a node with a due retrieval
 * sitting before a flagged node returned `retrieval_due` and the misconception
 * was never reached — the engine tested retention while a confirmed error
 * stood uncorrected.
 *
 * If the tier lived in config/pal_flow.php, a typo, a bad admin edit or a
 * hand-written profile row could unlock it and put that bug back. Because it
 * is a method on the handler, EsoFlowValidator reads the tier from the same
 * object that implements the behaviour, and the two cannot drift.
 *
 * Mirrors isConsequential() on App\Domain\Workflow\StepHandler, which exists
 * for the same reason: the engine asks the handler rather than inferring,
 * because only the handler knows.
 */
interface EsoFlowStageMeta
{
    /** Position is fixed and the stage cannot be switched off. */
    public const TIER_LOCKED = 'locked';

    /** May be switched off; position is fixed when on. */
    public const TIER_TOGGLEABLE = 'toggleable';

    /** May be reordered within its own band. */
    public const TIER_ORDERABLE = 'orderable';

    /** The stage key this handler serves; matches config/pal_flow.php. */
    public function key(): string;

    /** TIER_LOCKED | TIER_TOGGLEABLE | TIER_ORDERABLE. Never read from config. */
    public function tier(): string;

    /**
     * Stage keys that must resolve BEFORE this one, whatever the ranks say.
     *
     * A permutation can be perfectly valid as a permutation and still be
     * nonsense: mastery_verdict ahead of node_loop grades a concept before any
     * node has been served. Ranks alone cannot express that, so the validator
     * checks this partial order on top of the permutation check.
     *
     * @return array<int, string>
     */
    public function mustFollow(): array;

    /**
     * Stage keys that must be ENABLED for this one to be enabled.
     *
     * stale_mastery returns the `retrieval_due` payload
     * (EsoPolicyService.php:2970), so enabling it while retrieval is off would
     * serve an action the profile claims is disabled.
     *
     * @return array<int, string>
     */
    public function requires(): array;

    /**
     * Can this stage legitimately never fire?
     *
     * prerequisite_gate needs an authored `requires` relation; misconception
     * scan needs a mapped distractor; stale_mastery needs evidence to have
     * gone cold. The conformance suite's "no stage starves" assertion excludes
     * these, and reads the answer from here so that adding a stage cannot
     * silently opt itself out of that check.
     */
    public function isConditional(): bool;
}
