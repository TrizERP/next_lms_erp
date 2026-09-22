<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowNodeStage;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;

/**
 * Passes over a node that has met its threshold, its evidence floor AND its check (lines 1141-1146). Returns SKIP_NODE, never an action.
 *
 * TIER — LOCKED. Without it a saturated node is re-served forever, starving its siblings. The floor clause is what stops a diagnostic-inflated estimate skipping a node that has recorded no evidence.
 *
 */
class SettledSkipStage implements EsoFlowNodeStage
{
    public function key(): string
    {
        return 'settled_skip';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['stale_mastery'];
    }

    /** @return array<int, string> */
    public function requires(): array
    {
        return [];
    }

    public function isConditional(): bool
    {
        return true;
    }

    public function resolve(EsoFlowContext $ctx, ConceptNode $node, LearnerNodeState $state, array $params): ?array
    {
        // A node may only be passed over for being "good enough" once the
        // evidence floor masteryVerdict() will judge it against is satisfied.
        //
        // Testing the estimate alone deadlocked the engine: a diagnostic can
        // carry a node over its threshold at weight 2.0 while contributing zero
        // valid events, so every node was skipped here, the verdict then
        // withheld mastery for want of evidence, and the learner was told to
        // continue practising with no node to practise on.
        //
        // The checkSettled() clause is what makes Learn -> Practice -> Check
        // real: a node that practises its way to threshold arrives with its
        // check still open, and without this it would pass straight to the
        // verdict and the check would never be served at all.
        if ($state->isMastered()
            || ($ctx->engine->hasSatisfiedOwnThreshold($node, $state)
                && $ctx->engine->nodeMeetsEvidenceFloor($node, $ctx->evidence())
                && $ctx->engine->checkSettled($node, $state, $ctx->subInstituteId))) {
            return self::SKIP_NODE;
        }

        return null;
    }
}
