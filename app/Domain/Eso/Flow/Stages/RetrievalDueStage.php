<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowNodeStage;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;

/**
 * D5. A mastered node whose scheduled review has come due (line 1103).
 *
 * TIER — TOGGLEABLE. A school may run without spaced retention. Position is fixed: a review that fires after practice is not a delayed retrieval.
 *
 */
class RetrievalDueStage implements EsoFlowNodeStage
{
    public function key(): string
    {
        return 'retrieval_due';
    }

    public function tier(): string
    {
        return self::TIER_TOGGLEABLE;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return [];
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
        // Retrieval ELIGIBILITY, not a mastery test. isMastered() covers both
        // `mastered` and `retained`: testing STATUS_MASTERED alone meant a
        // retained node never came due again, so the ladder [2, 7, 30, 60, 180]
        // could only ever deliver its first rung.
        if (! $state->isMastered()
            || $state->next_review_at === null
            || ! $state->next_review_at->lte(now())) {
            return null;
        }

        return $ctx->engine->retrievalDueAction(
            $ctx->studentId,
            $ctx->conceptId,
            $node,
            $state,
            $ctx->subInstituteId,
            $ctx->silent
        );
    }
}
