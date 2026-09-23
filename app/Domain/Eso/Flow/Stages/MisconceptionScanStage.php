<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

use App\Models\Eso\LearnerNodeState;

/**
 * D3. Scans the WHOLE concept for a flagged misconception before any node is served (lines 1079-1085).
 *
 * TIER — LOCKED. This is the hoist. While it lived inside the node loop, precedence depended on sort_order and a due retrieval could outrank a confirmed error - the engine tested retention while a misconception stood uncorrected. Its position IS the fix.
 *
 */
class MisconceptionScanStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'misconception_scan';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['prerequisite_gate'];
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
        // CONCEPT-WIDE, and that is the whole point of this being a stage of
        // its own rather than part of the node loop.
        //
        // This scan used to live inside the loop, which made precedence depend
        // on pal_concept_nodes.sort_order: a node with a due retrieval sitting
        // before a flagged node returned `retrieval_due` and the misconception
        // was never reached - the engine tested retention while a confirmed
        // error stood uncorrected. Hoisting it is the fix, and keeping it
        // LOCKED at this position is what stops a profile undoing that.
        foreach ($ctx->nodes as $node) {
            $state = $ctx->states()->get($node->id)
                ?? $ctx->engine->stateFor($ctx->studentId, (int) $node->id, $ctx->subInstituteId);

            if ($state->status === LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED) {
                return $ctx->engine->reserveContrastPairAction(
                    $ctx->studentId,
                    $ctx->conceptId,
                    $node,
                    $state,
                    $ctx->subInstituteId,
                    $ctx->silent
                );
            }
        }

        return null;
    }
}
