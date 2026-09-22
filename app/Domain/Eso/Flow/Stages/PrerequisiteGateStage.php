<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

/**
 * D2. Blocks a concept whose prerequisites are unmet, and probes one whose evidence has gone stale (line 1044).
 *
 * TIER — LOCKED. Teaching a concept whose prerequisite is missing is not a preference, it is a defect. ADR-002 fixes its precedence above D3 deliberately.
 *
 */
class PrerequisiteGateStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'prerequisite_gate';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['diagnostic_entry'];
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

    public function resolve(EsoFlowContext $ctx, array $params): ?array
    {
        // The gate already returns null for "prerequisites are fine, carry on",
        // which is this interface's contract too - so it passes straight
        // through with no translation.
        return $ctx->engine->prerequisiteGate(
            $ctx->studentId,
            $ctx->conceptId,
            $ctx->subInstituteId,
            $ctx->silent
        );
    }
}
