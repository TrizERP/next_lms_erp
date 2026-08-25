<?php

namespace App\Domain\AI\Outcomes;

use App\Services\Mcp\McpRequestContext;

/**
 * Reads one measurable metric from the real tables.
 *
 * A metric that has no resolver cannot be measured, which is why EsoBindingRule
 * insists a recommendation names a metric key: the name is the contract between what
 * was promised and what can be checked.
 */
interface MetricResolver
{
    /** Matches `ai_outcomes.metric_key`. */
    public function metricKey(): string;

    public function label(): string;

    /**
     * The metric's current value for a subject, or null when it cannot be read.
     *
     * Null must mean "unknown", never "zero" — OutcomeTracker treats null as
     * inconclusive, and returning 0.0 for missing data would score an intervention
     * as a catastrophic failure.
     */
    public function resolve(
        string $subjectEntityKey,
        int|string $subjectId,
        McpRequestContext $scope
    ): ?float;
}
