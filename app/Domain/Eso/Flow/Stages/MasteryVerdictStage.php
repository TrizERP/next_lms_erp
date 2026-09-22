<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

/**
 * D4. The terminal verdict, reached when every node is settled (line 1177).
 *
 * TIER — LOCKED. The mastery rule is identical for every institute so attainment stays comparable across the estate. No threshold or evidence-floor field exists in config for a validator to accept.
 *
 */
class MasteryVerdictStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'mastery_verdict';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['node_loop'];
    }

    /** @return array<int, string> */
    public function requires(): array
    {
        return [];
    }

    public function isConditional(): bool
    {
        return false;
    }

    public function resolve(EsoFlowContext $ctx, array $params): ?array
    {
        // Terminal and idempotent: if the concept is already mastered this
        // confirms it and stops practice.
        //
        // `offer_enrichment` and `offer_next_concept` are read INSIDE
        // masteryVerdict(), not here, because both are already gated on
        // `$mastered && ! $silent` there - resolving them on the silent path
        // recurses without bound (the docblock at lines 2549-2557).
        return $ctx->engine->masteryVerdict(
            $ctx->studentId,
            $ctx->conceptId,
            $ctx->subInstituteId,
            $ctx->silent
        );
    }
}
