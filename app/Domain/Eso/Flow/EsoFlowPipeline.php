<?php

namespace App\Domain\Eso\Flow;

use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;
use LogicException;

/**
 * Walks an institute's resolved flow and returns the first action a stage
 * produces.
 *
 * This is the whole engine-ordering mechanism, and it is deliberately small:
 * the cascade's complexity was never in the walking, it was in the ORDER and
 * in what each guard decides. The order is now data (EsoFlowPlan) and each
 * decision still lives where it always did (EsoPolicyService, reached through
 * EsoFlowPort). What is left here is a loop.
 *
 * ---------------------------------------------------------------------------
 * THE NULL CONTRACT
 * ---------------------------------------------------------------------------
 * A stage returning NULL means "not my business right now, ask the next one".
 * That is not a new convention invented for the pipeline — prerequisiteGate()
 * has had exactly this contract since it was written (EsoPolicyService.php:1511),
 * and the hardcoded cascade already treats a null gate as "carry on".
 *
 * ---------------------------------------------------------------------------
 * WHY IT THROWS WHEN NOTHING RESOLVES
 * ---------------------------------------------------------------------------
 * The concept pipeline must always produce an action, because the caller is a
 * learner waiting for a screen. mastery_verdict is LOCKED, terminal and always
 * returns, so falling off the end is impossible unless the validator has been
 * bypassed — a profile written straight into the database, say.
 *
 * Throwing there rather than returning a placeholder is the same judgement
 * StepHandlerRegistry records: "a missing handler is a configuration error, and
 * pretending the step succeeded would let a workflow claim to have done
 * something it never did." A learner served a silent no-op is worse than a
 * learner served an error, because nobody finds out.
 */
class EsoFlowPipeline
{
    public function __construct(private readonly EsoFlowStageRegistry $registry)
    {
    }

    /**
     * Run the concept pipeline: the stages that resolve once per nextAction().
     *
     * @return array<string,mixed>
     */
    public function run(EsoFlowContext $ctx): array
    {
        foreach ($ctx->plan->conceptStageKeys() as $key) {
            $stage = $this->registry->find($key);

            if (! $stage instanceof EsoFlowStage) {
                throw new LogicException(
                    "Flow stage '{$key}' is declared in the concept pipeline but does not "
                    . 'implement EsoFlowStage.'
                );
            }

            $resolved = $stage->resolve($ctx, $ctx->plan->params($key));

            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new LogicException(sprintf(
            "Flow profile '%s' resolved no action for concept %d. The terminal stage is LOCKED "
            . 'and always returns, so this means the profile was written without passing through '
            . 'EsoFlowValidator.',
            $ctx->plan->profileKey(),
            $ctx->conceptId
        ));
    }

    /**
     * Run the node pipeline for ONE node.
     *
     * Returns null when no stage claimed the node, and the SKIP_NODE sentinel
     * when a stage asked for it to be passed over. NodeLoopStage treats those
     * the same way — it is the only thing that interprets them, because loop
     * control belongs to the loop rather than to a stage.
     *
     * @return array<string,mixed>|null
     */
    public function runNode(EsoFlowContext $ctx, ConceptNode $node, LearnerNodeState $state): ?array
    {
        foreach ($ctx->plan->nodeStageKeys() as $key) {
            $stage = $this->registry->find($key);

            if (! $stage instanceof EsoFlowNodeStage) {
                throw new LogicException(
                    "Flow stage '{$key}' is declared in the node pipeline but does not "
                    . 'implement EsoFlowNodeStage.'
                );
            }

            $resolved = $stage->resolve($ctx, $node, $state, $ctx->plan->params($key));

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }
}
