<?php

namespace App\Domain\Eso\Flow\Stages;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;

/**
 * Cold start: no learner state exists for this concept, so the entry diagnostic is served (line 1035).
 *
 * TIER — TOGGLEABLE. The clearest legitimate school request there is - 'we place students with our own entrance test'. Position stays fixed: a diagnostic after teaching is not a diagnostic.
 *
 */
class DiagnosticEntryStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'diagnostic_entry';
    }

    public function tier(): string
    {
        return self::TIER_TOGGLEABLE;
    }

    /** @return array<int, string> */
    public function mustFollow(): array
    {
        return ['nodes_present'];
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
        // First touch of the concept: nothing has been diagnosed yet.
        //
        // Reading states() here is what keeps the one-query `no_nodes_defined`
        // path cheap - the context loads them lazily, so a concept that exits
        // at the stage before this one never pays for them.
        if ($ctx->states()->isNotEmpty()) {
            return null;
        }

        $ctx->log(null, [], 'D1: concept entry, no diagnostic on file', 'diagnostic');

        return $ctx->engine->respond('diagnostic', $ctx->conceptId, null, 'D1', null);
    }
}
