<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\AI\Outcomes\OutcomeTracker;
use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Captures the baseline that makes the outcome measurable later.
 *
 * Placed after the action in a workflow, it records where the metric stood at the
 * moment the intervention began. Without this step the outcome row exists but has
 * nothing to compare against, and OutcomeTracker will honestly score it
 * inconclusive — which is why the seeded workflows include it.
 *
 * Reading a metric changes nothing, so this is not consequential.
 */
class MeasureStepHandler implements StepHandler
{
    public function __construct(private readonly OutcomeTracker $outcomes)
    {
    }

    public function type(): string
    {
        return 'measure';
    }

    public function isConsequential(array $config): bool
    {
        return false;
    }

    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult {
        if ($run->recommendationId === null || ! Schema::hasTable('ai_outcomes')) {
            return StepResult::skipped('No outcome is attached to this run.');
        }

        $outcomes = DB::table('ai_outcomes')
            ->where('recommendation_id', $run->recommendationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->get();

        if ($outcomes->isEmpty()) {
            return StepResult::skipped('This recommendation declared no measurable outcome.');
        }

        $captured = [];

        foreach ($outcomes as $outcome) {
            $baseline = $this->outcomes->captureBaseline((int) $outcome->id, $scope);

            $captured[] = [
                'outcome_id' => (int) $outcome->id,
                'metric_key' => $outcome->metric_key,
                'baseline_value' => $baseline,
                'measure_after' => $outcome->measure_after,
            ];

            if (Schema::hasTable('workflow_outcomes')) {
                DB::table('workflow_outcomes')->insert([
                    'run_id' => $run->runId,
                    'outcome_id' => (int) $outcome->id,
                    'metric_key' => $outcome->metric_key,
                    'status' => $baseline === null ? 'pending' : 'measuring',
                    'detail' => json_encode(['baseline_value' => $baseline]),
                    'sub_institute_id' => $scope->selectedInstituteId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // A metric with no resolver is recorded rather than hidden — it tells an
        // administrator that a promised measurement cannot actually be taken.
        $unmeasurable = array_values(array_filter(
            $captured,
            fn (array $entry) => $entry['baseline_value'] === null
        ));

        return StepResult::completed(
            ['outcomes' => $captured, 'unmeasurable' => $unmeasurable],
            $unmeasurable === []
                ? 'Baseline captured.'
                : sprintf('Baseline captured; %d metric(s) could not be read.', count($unmeasurable))
        );
    }
}
