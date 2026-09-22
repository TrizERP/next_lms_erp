<?php

namespace App\Domain\Eso\Flow;

use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;

/**
 * One guard of the NODE-level pipeline — the stages that run per node inside
 * node_loop: retrieval_due, stale_mastery, settled_skip, phase_machine.
 *
 * A separate interface rather than a flag on EsoFlowStage because the
 * granularity genuinely differs: these receive the node and its state, and the
 * concept-level stages have no node to receive. Flattening the two into one
 * ordered list is the design error that only surfaces when someone tries to
 * write the validator — a node stage's rank is meaningless against a concept
 * stage's rank, because they never compete.
 *
 * SETTLED_SKIP IS THE ODD ONE. It does not resolve an action; it decides
 * whether the loop should pass over this node entirely. It signals that by
 * returning the sentinel below, which only NodeLoopStage interprets —
 * loop control stays owned by the loop, not by the stage.
 */
interface EsoFlowNodeStage extends EsoFlowStageMeta
{
    /**
     * Returned by a stage that wants the node loop to move to the next node
     * without serving anything. Corresponds to the bare `continue` at
     * EsoPolicyService.php:1141-1146.
     */
    public const SKIP_NODE = ['__flow' => 'skip_node'];

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>|null
     */
    public function resolve(EsoFlowContext $ctx, ConceptNode $node, LearnerNodeState $state, array $params): ?array;
}
