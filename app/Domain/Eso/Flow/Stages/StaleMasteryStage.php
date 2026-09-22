<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowNodeStage;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;

/**
 * Mastery is held but its newest evidence is outside the recency window, so the node is re-verified (line 1116).
 *
 * TIER — TOGGLEABLE. Requires retrieval_due because it returns that stage's payload (line 2970) - enabling it alone would serve an action the profile claims is off.
 *
 */
class StaleMasteryStage implements EsoFlowNodeStage
{
    public function key(): string
    {
        return 'stale_mastery';
    }

    public function tier(): string
    {
        return self::TIER_TOGGLEABLE;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['retrieval_due'];
    }

    /** @return array<int, string> */
    public function requires(): array
    {
        return ['retrieval_due'];
    }

    public function isConditional(): bool
    {
        return true;
    }

    public function resolve(EsoFlowContext $ctx, ConceptNode $node, LearnerNodeState $state, array $params): ?array
    {
        // Wins over "skip past, concept mastered" because the next step is
        // verification, not silence. Writes nothing: the retrieval_due and
        // content_unavailable routes are the same handlers the scheduled path
        // uses, and the only DB effect comes from retrievalCheck() being called.
        if (! $state->isMastered() || ! $ctx->conceptStale()) {
            return null;
        }

        return $ctx->engine->staleMasteryAction(
            $ctx->studentId,
            $ctx->conceptId,
            $node,
            $state,
            $ctx->subInstituteId,
            $ctx->silent
        );
    }
}
