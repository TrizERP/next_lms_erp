<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

use App\Domain\Eso\Flow\EsoFlowNodeStage;

use App\Domain\Eso\Flow\EsoFlowPipeline;

/**
 * Walks the concept's nodes, running the node pipeline against each, and owns the content_unavailable hold-back (lines 1089-1173).
 *
 * TIER — LOCKED. The only iterating stage. Disabling it would leave nothing to serve; moving it past the verdict would grade before serving.
 *
 */
class NodeLoopStage implements EsoFlowStage
{
    /**
     * The pipeline is injected rather than reached through the context,
     * because this is the ONE stage that runs other stages.
     *
     * No container cycle: EsoFlowPipeline depends only on the stage
     * REGISTRY, which holds class names and resolves them lazily, so
     * building this stage does not build its siblings.
     */
    public function __construct(private readonly EsoFlowPipeline $pipeline)
    {
    }

    public function key(): string
    {
        return 'node_loop';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['misconception_scan'];
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
        // The first node that wanted to serve but had no content. Held back so
        // a servable sibling wins, and only returned if none exists.
        $blocked = null;

        foreach ($ctx->nodes as $node) {
            $state = $ctx->states()->get($node->id)
                ?? $ctx->engine->stateFor($ctx->studentId, (int) $node->id, $ctx->subInstituteId);

            $resolved = $this->pipeline->runNode($ctx, $node, $state);

            // Nothing wanted this node, or settled_skip passed over it.
            if ($resolved === null || $resolved === EsoFlowNodeStage::SKIP_NODE) {
                continue;
            }

            // A node with no answerable item must not hold the whole concept
            // hostage. It cannot accumulate evidence, so it can never clear the
            // floor and would be re-selected for ever, starving every sibling
            // behind it - the S-node case in
            // docs/CHAPTER_1014_NODE_CONTENT_HEALTH_REPORT.md.
            //
            // Remember the first one and carry on looking.
            if (($resolved['action'] ?? null) === 'content_unavailable') {
                $blocked ??= $resolved;

                continue;
            }

            return $resolved;
        }

        // Nothing servable anywhere, but something was waiting on content: say
        // so, rather than letting the cascade fall through to a mastery verdict
        // the learner was never given the means to earn.
        //
        // Returning null instead hands control to the next stage, which is the
        // verdict - exactly the fall-through at line 1177.
        return $blocked;
    }
}
