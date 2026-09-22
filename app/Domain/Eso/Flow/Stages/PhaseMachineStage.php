<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowNodeStage;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;

/**
 * Runs the Learn / Practice / Check sequence for one node, via phaseFor() (line 1148).
 *
 * TIER — LOCKED. The phase ORDER inside it is configurable; the existence of a phase machine is not. This is where a school's reordering actually lands.
 *
 */
class PhaseMachineStage implements EsoFlowNodeStage
{
    public function key(): string
    {
        return 'phase_machine';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['settled_skip'];
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

    public function resolve(EsoFlowContext $ctx, ConceptNode $node, LearnerNodeState $state, array $params): ?array
    {
        // Always resolves something - teachOrPracticeAction() returns an action
        // on every path, including content_unavailable, which NodeLoopStage
        // holds back rather than serving.
        //
        // The evidence is read off the context so it is resolved once for the
        // whole concept rather than per node. Re-querying here would still
        // return the right action, which is exactly why the parity harness
        // counts queries rather than trusting that.
        return $ctx->engine->teachOrPracticeAction(
            $ctx->studentId,
            $ctx->conceptId,
            $node,
            $state,
            $ctx->subInstituteId,
            $ctx->evidence(),
            $ctx->silent
        );
    }
}
