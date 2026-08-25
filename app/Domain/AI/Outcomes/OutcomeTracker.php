<?php

namespace App\Domain\AI\Outcomes;

use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the loop.
 *
 * An outcome row is created when a recommendation is approved (DecisionGate seeds it
 * from the ESO binding) and sits pending until its measurement horizon passes. This
 * class captures the baseline at approval time and the observation afterwards, then
 * scores the difference.
 *
 * Scoring is deliberately conservative. Without a baseline there is no honest verdict,
 * so the outcome is marked inconclusive rather than guessed at — a learning loop fed
 * on optimistic scoring learns the wrong thing.
 *
 * Metric resolution is pluggable: `MetricResolver` implementations know how to read
 * one metric from the real tables, so adding a measurable outcome does not mean
 * editing this class.
 */
class OutcomeTracker
{
    /** @var array<string, MetricResolver> */
    private array $resolvers = [];

    /**
     * @param  iterable<MetricResolver>  $resolvers
     */
    public function __construct(
        private readonly AiAuditLogger $audit,
        iterable $resolvers = [],
    ) {
        foreach ($resolvers as $resolver) {
            $this->resolvers[$resolver->metricKey()] = $resolver;
        }
    }

    public function registerResolver(MetricResolver $resolver): void
    {
        $this->resolvers[$resolver->metricKey()] = $resolver;
    }

    /**
     * Record the starting value, so there is something to compare against later.
     */
    public function captureBaseline(int $outcomeId, McpRequestContext $scope): ?float
    {
        if (! Schema::hasTable('ai_outcomes')) {
            return null;
        }

        $outcome = DB::table('ai_outcomes')
            ->where('id', $outcomeId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->first();

        if (! $outcome || $outcome->baseline_value !== null) {
            return $outcome?->baseline_value === null ? null : (float) $outcome->baseline_value;
        }

        $value = $this->readMetric($outcome->metric_key, $outcome->subject_entity_key, $outcome->subject_id, $scope);

        if ($value === null) {
            return null;
        }

        DB::table('ai_outcomes')->where('id', $outcomeId)->update([
            'baseline_value' => $value,
            'baseline_at' => now(),
            'status' => 'measuring',
            'updated_at' => now(),
        ]);

        return $value;
    }

    /**
     * Measure everything whose horizon has passed.
     *
     * @return array{measured:int, improved:int, worsened:int, unchanged:int, inconclusive:int}
     */
    public function measureDue(McpRequestContext $scope, int $limit = 100): array
    {
        $tally = ['measured' => 0, 'improved' => 0, 'worsened' => 0, 'unchanged' => 0, 'inconclusive' => 0];

        if (! Schema::hasTable('ai_outcomes')) {
            return $tally;
        }

        $due = DB::table('ai_outcomes')
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->whereIn('status', ['pending', 'measuring'])
            ->whereNotNull('measure_after')
            ->where('measure_after', '<=', now())
            ->orderBy('measure_after')
            ->limit($limit)
            ->get();

        foreach ($due as $outcome) {
            $status = $this->measure((int) $outcome->id, $scope);
            $tally['measured']++;

            if (isset($tally[$status])) {
                $tally[$status]++;
            }
        }

        return $tally;
    }

    /**
     * Measure one outcome and score it.
     *
     * @return string improved | worsened | unchanged | inconclusive
     */
    public function measure(int $outcomeId, McpRequestContext $scope): string
    {
        $outcome = DB::table('ai_outcomes')
            ->where('id', $outcomeId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->first();

        if (! $outcome) {
            return 'inconclusive';
        }

        $observed = $this->readMetric(
            $outcome->metric_key,
            $outcome->subject_entity_key,
            $outcome->subject_id,
            $scope
        );

        $detail = $outcome->detail ? json_decode($outcome->detail, true) : [];
        $direction = strtolower((string) ($detail['direction'] ?? 'increase'));

        $status = $this->score(
            $outcome->baseline_value === null ? null : (float) $outcome->baseline_value,
            $observed,
            $direction
        );

        $delta = ($observed !== null && $outcome->baseline_value !== null)
            ? round($observed - (float) $outcome->baseline_value, 4)
            : null;

        DB::table('ai_outcomes')->where('id', $outcomeId)->update([
            'observed_value' => $observed,
            'observed_at' => now(),
            'delta' => $delta,
            'status' => $status,
            'updated_at' => now(),
        ]);

        $this->audit->record('outcome.measured', $scope, [
            'actor_type' => 'system',
            'related_type' => 'ai_outcomes',
            'related_id' => $outcomeId,
            'subject_entity_key' => $outcome->subject_entity_key,
            'subject_id' => $outcome->subject_id,
            'message' => sprintf('Outcome %s: %s.', $outcome->metric_key, $status),
            'payload' => [
                'baseline' => $outcome->baseline_value,
                'observed' => $observed,
                'delta' => $delta,
                'direction' => $direction,
            ],
        ]);

        return $status;
    }

    /**
     * Outcomes for a subject — what the student profile's Outcomes tab shows.
     */
    public function forSubject(
        string $subjectEntityKey,
        int|string $subjectId,
        McpRequestContext $scope,
        int $limit = 25
    ): array {
        if (! Schema::hasTable('ai_outcomes')) {
            return [];
        }

        return DB::table('ai_outcomes')
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'case_id' => $row->case_id ? (int) $row->case_id : null,
                'recommendation_id' => $row->recommendation_id ? (int) $row->recommendation_id : null,
                'metric_key' => $row->metric_key,
                'metric_label' => $row->metric_label,
                'baseline_value' => $row->baseline_value === null ? null : (float) $row->baseline_value,
                'baseline_at' => $row->baseline_at,
                'target_value' => $row->target_value === null ? null : (float) $row->target_value,
                'observed_value' => $row->observed_value === null ? null : (float) $row->observed_value,
                'observed_at' => $row->observed_at,
                'delta' => $row->delta === null ? null : (float) $row->delta,
                'status' => $row->status,
                'measure_after' => $row->measure_after,
                'detail' => $row->detail ? json_decode($row->detail, true) : null,
            ])
            ->all();
    }

    /**
     * The learning signal: how well a given action type has actually worked.
     *
     * This is what makes the loop a loop — over time it says which interventions
     * moved the metric and which did not.
     */
    public function effectivenessByActionType(McpRequestContext $scope, ?string $caseType = null): array
    {
        if (! Schema::hasTable('ai_outcomes') || ! Schema::hasTable('ai_recommendations')) {
            return [];
        }

        $query = DB::table('ai_outcomes')
            ->join('ai_recommendations', 'ai_recommendations.id', '=', 'ai_outcomes.recommendation_id')
            ->where('ai_outcomes.sub_institute_id', $scope->selectedInstituteId)
            ->whereIn('ai_outcomes.status', ['improved', 'unchanged', 'worsened']);

        if ($caseType !== null) {
            $query->join('ai_cases', 'ai_cases.id', '=', 'ai_outcomes.case_id')
                ->where('ai_cases.case_type', $caseType);
        }

        return $query->groupBy('ai_recommendations.action_type', 'ai_outcomes.status')
            ->select(
                'ai_recommendations.action_type',
                'ai_outcomes.status',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(ai_outcomes.delta) as average_delta')
            )
            ->get()
            ->groupBy('action_type')
            ->map(function ($rows) {
                $counts = [];
                $total = 0;

                foreach ($rows as $row) {
                    $counts[$row->status] = (int) $row->total;
                    $total += (int) $row->total;
                }

                return [
                    'counts' => $counts,
                    'total' => $total,
                    'improvement_rate' => $total > 0
                        ? round((($counts['improved'] ?? 0) / $total), 4)
                        : null,
                ];
            })
            ->all();
    }

    private function readMetric(
        string $metricKey,
        string $subjectEntityKey,
        int|string $subjectId,
        McpRequestContext $scope
    ): ?float {
        $resolver = $this->resolvers[$metricKey] ?? null;

        return $resolver?->resolve($subjectEntityKey, $subjectId, $scope);
    }

    /**
     * Without a baseline or an observation there is no honest verdict.
     */
    private function score(?float $baseline, ?float $observed, string $direction): string
    {
        if ($baseline === null || $observed === null) {
            return 'inconclusive';
        }

        $delta = $observed - $baseline;

        // A change smaller than this is noise, not an effect.
        $tolerance = max(0.01, abs($baseline) * 0.02);

        if (abs($delta) < $tolerance) {
            return $direction === 'maintain' ? 'improved' : 'unchanged';
        }

        return match ($direction) {
            'decrease' => $delta < 0 ? 'improved' : 'worsened',
            'maintain' => 'worsened',
            default => $delta > 0 ? 'improved' : 'worsened',
        };
    }
}
