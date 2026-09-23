<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

/**
 * Refuses to resolve a concept with no K/A/S nodes authored, rather than reporting a mastery verdict over an empty set (EsoPolicyService.php:1022-1030).
 *
 * TIER — LOCKED. A precondition. Everything after it assumes nodes exist; a profile that disabled it would hand every later stage an empty collection.
 *
 */
class NodesPresentStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'nodes_present';
    }

    public function tier(): string
    {
        return self::TIER_LOCKED;
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

    public function resolve(EsoFlowContext $ctx, array $params): ?array
    {
        if ($ctx->nodes->isNotEmpty()) {
            return null;
        }

        // Deliberately unlogged, matching line 1024. This is an authoring gap
        // rather than a decision about a learner, and eso_decision_log is the
        // record of decisions.
        return $ctx->engine->respond(
            'no_nodes_defined',
            $ctx->conceptId,
            null,
            'ESO: concept has no K/A/S nodes authored yet',
            null
        );
    }
}
