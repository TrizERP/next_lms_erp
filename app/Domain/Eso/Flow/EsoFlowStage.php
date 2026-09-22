<?php

namespace App\Domain\Eso\Flow;

/**
 * One guard of the CONCEPT-level cascade — the stages nextAction() runs once
 * per resolve: nodes_present, diagnostic_entry, prerequisite_gate,
 * misconception_scan, node_loop, mastery_verdict.
 *
 * Returning NULL means "not my business right now, ask the next stage". That
 * is not a new convention: prerequisiteGate() already has exactly this
 * contract (EsoPolicyService.php:1511), and the cascade already treats a null
 * gate as "carry on". A respond()-shaped array means the cascade stops here
 * and that action is what the learner gets.
 */
interface EsoFlowStage extends EsoFlowStageMeta
{
    /**
     * @param  array<string,mixed>  $params  resolved parameters for this stage
     * @return array<string,mixed>|null
     */
    public function resolve(EsoFlowContext $ctx, array $params): ?array;
}
