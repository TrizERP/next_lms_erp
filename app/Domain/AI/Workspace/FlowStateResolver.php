<?php

namespace App\Domain\AI\Workspace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a record sits in the intelligence chain, and what was captured at each stage.
 *
 *   Signal → Evidence → Case → Explain → Recommend → Decision → Action → Outcome
 *
 * The stage is **derived**, never stored. There is deliberately no `flow_stage` column
 * anywhere: a duplicated status field is a field that goes stale the first time a row
 * is written by something that forgot to update it. Reading the actual rows means the
 * strip cannot lie about what exists.
 *
 * Each stage reports three things: whether it is done, what was captured (counts and
 * references a person can check), and — for the one stage that is current — the single
 * next action that is valid. That last part is what gives the chat its structured
 * logic: at any moment there is exactly one sensible next move, and the panel offers
 * that one rather than all five capabilities at once.
 */
class FlowStateResolver
{
    public const STAGES = [
        'signal', 'evidence', 'case', 'explanation',
        'recommendation', 'decision', 'action', 'outcome',
    ];

    /**
     * @return array{
     *   applicable:bool, stage:string|null, stages:array<int,array>,
     *   next:array|null, refs:array
     * }
     */
    public function resolve(AiContext $context): array
    {
        if (! $context->hasEntity()) {
            // A flow is about one record. On a list page there is nothing to track.
            return [
                'applicable' => false,
                'stage' => null,
                'stages' => [],
                'next' => null,
                'refs' => [],
            ];
        }

        $scope = $context->scope;
        $entityKey = $context->entityKey;
        $entityId = $context->entityId;
        $tenant = $scope->selectedInstituteId;

        $signals = $this->countSignals($entityKey, $entityId, $tenant);
        $case = $this->latestCase($entityKey, $entityId, $tenant);
        $caseId = $case?->id;

        $evidence = $this->countEvidence($entityKey, $entityId, $tenant);
        $explanation = $caseId ? $this->latestExplanation($caseId, $tenant) : null;
        $recommendation = $this->latestRecommendation($entityKey, $entityId, $tenant);
        $decision = $recommendation ? $this->latestDecision((int) $recommendation->id, $tenant) : null;
        $workflowRun = $this->latestWorkflowRun($entityKey, $entityId, $tenant);
        $intervention = $this->latestIntervention($entityKey, $entityId, $tenant);
        $outcome = $this->latestOutcome($entityKey, $entityId, $tenant);

        $captured = [
            'signal' => [
                'done' => $signals['total'] > 0,
                'summary' => $signals['total'] > 0
                    ? sprintf('%d signal%s detected', $signals['total'], $signals['total'] === 1 ? '' : 's')
                    : 'No signals detected yet',
                'data' => $signals,
            ],
            'evidence' => [
                'done' => $evidence['verified'] > 0,
                'summary' => $evidence['verified'] > 0
                    ? sprintf('%d verified item%s', $evidence['verified'], $evidence['verified'] === 1 ? '' : 's')
                    : 'No evidence gathered yet',
                'data' => $evidence,
            ],
            'case' => [
                'done' => $case !== null,
                'summary' => $case
                    ? sprintf('%s · %s', $case->case_reference, $case->severity)
                    : 'No case opened',
                'data' => $case ? [
                    'id' => (int) $case->id,
                    'reference' => $case->case_reference,
                    'severity' => $case->severity,
                    'status' => $case->status,
                    'title' => $case->title,
                ] : null,
            ],
            'explanation' => [
                // Only a governance-passing explanation counts. One that was refused is
                // recorded but does not advance the flow — the system has not yet said
                // anything it can defend.
                'done' => $explanation !== null && (bool) $explanation->governance_passed,
                'summary' => $explanation
                    ? ((bool) $explanation->governance_passed
                        ? 'Evidence-backed explanation ready'
                        : 'Explanation withheld — could not be evidenced')
                    : 'Not explained yet',
                'data' => $explanation ? [
                    'id' => (int) $explanation->id,
                    'governance_passed' => (bool) $explanation->governance_passed,
                    'narrative' => $explanation->narrative,
                ] : null,
            ],
            'recommendation' => [
                'done' => $recommendation !== null && (bool) $recommendation->governance_passed,
                'summary' => $recommendation
                    ? sprintf('%s · %s', $recommendation->title, $this->humanize($recommendation->status))
                    : 'Nothing recommended yet',
                'data' => $recommendation ? [
                    'id' => (int) $recommendation->id,
                    'reference' => $recommendation->recommendation_reference,
                    'title' => $recommendation->title,
                    'status' => $recommendation->status,
                    'risk_level' => $recommendation->risk_level,
                    'requires_approval' => (bool) $recommendation->requires_approval,
                    'governance_passed' => (bool) $recommendation->governance_passed,
                    'workflow_key' => $recommendation->workflow_key,
                ] : null,
            ],
            'decision' => [
                'done' => $decision !== null && $decision->decision === 'approved',
                'summary' => $decision
                    ? sprintf('%s by %s', $this->humanize($decision->decision), $decision->decided_by_name ?: 'a user')
                    : 'Awaiting a human decision',
                'data' => $decision ? [
                    'id' => (int) $decision->id,
                    'decision' => $decision->decision,
                    'decided_by' => (int) $decision->decided_by,
                    'decided_by_name' => $decision->decided_by_name,
                    'decided_at' => $decision->decided_at,
                ] : null,
            ],
            'action' => [
                'done' => $intervention !== null,
                'summary' => $intervention
                    ? sprintf('%s · %s', $intervention->intervention_reference, $this->humanize($intervention->status))
                    : ($workflowRun ? 'Process running' : 'Nothing created yet'),
                'data' => [
                    'intervention' => $intervention ? [
                        'id' => (int) $intervention->id,
                        'reference' => $intervention->intervention_reference,
                        'status' => $intervention->status,
                    ] : null,
                    'workflow_run' => $workflowRun ? [
                        'id' => (int) $workflowRun->id,
                        'reference' => $workflowRun->run_reference,
                        'status' => $workflowRun->status,
                        'current_step_key' => $workflowRun->current_step_key,
                    ] : null,
                ],
            ],
            'outcome' => [
                'done' => $outcome !== null && in_array($outcome->status, ['improved', 'unchanged', 'worsened'], true),
                'summary' => $outcome
                    ? $this->outcomeSummary($outcome)
                    : 'Nothing being measured',
                'data' => $outcome ? [
                    'id' => (int) $outcome->id,
                    'metric_key' => $outcome->metric_key,
                    'metric_label' => $outcome->metric_label,
                    'baseline_value' => $outcome->baseline_value === null ? null : (float) $outcome->baseline_value,
                    'observed_value' => $outcome->observed_value === null ? null : (float) $outcome->observed_value,
                    'delta' => $outcome->delta === null ? null : (float) $outcome->delta,
                    'status' => $outcome->status,
                    'measure_after' => $outcome->measure_after,
                ] : null,
            ],
        ];

        $currentStage = $this->currentStage($captured);

        $stages = [];

        foreach (self::STAGES as $key) {
            $entry = $captured[$key];

            $stages[] = [
                'key' => $key,
                'label' => $this->stageLabel($key),
                'status' => $entry['done']
                    ? 'complete'
                    : ($key === $currentStage ? 'current' : 'pending'),
                'summary' => $entry['summary'],
                'data' => $entry['data'],
            ];
        }

        return [
            'applicable' => true,
            'stage' => $currentStage,
            'stages' => $stages,
            'next' => $this->nextAction($currentStage, $captured, $context),
            'refs' => [
                'case_id' => $caseId ? (int) $caseId : null,
                'recommendation_id' => $recommendation ? (int) $recommendation->id : null,
                'workflow_run_id' => $workflowRun ? (int) $workflowRun->id : null,
                'outcome_id' => $outcome ? (int) $outcome->id : null,
            ],
        ];
    }

    /**
     * The first stage that is not yet done. Everything before it is complete;
     * everything after is unreachable until it is.
     */
    private function currentStage(array $captured): string
    {
        foreach (self::STAGES as $key) {
            if (! $captured[$key]['done']) {
                return $key;
            }
        }

        // The whole chain has run. Measuring again is the only thing left.
        return 'outcome';
    }

    /**
     * The single valid next move.
     *
     * This is what stops the panel offering all five capabilities for every question.
     * Before a case exists, the answer is "analyse" — not "start a workflow". After
     * approval, it is "watch the process" — not "recommend again".
     */
    private function nextAction(string $stage, array $captured, AiContext $context): ?array
    {
        $recommendation = $captured['recommendation']['data'] ?? null;

        return match ($stage) {
            'signal', 'evidence', 'case' => [
                'capability' => 'agent',
                'label' => 'Analyse academic risk',
                'hint' => 'Look at assessments, attendance and assigned work to see whether there is a problem.',
                'action_type' => 'run_agent',
            ],
            'explanation' => [
                'capability' => 'agent',
                'label' => 'Re-run the analysis',
                'hint' => 'A case exists but has no explanation that could be backed by evidence.',
                'action_type' => 'run_agent',
            ],
            'recommendation' => [
                'capability' => 'agent',
                'label' => 'Re-run the analysis',
                'hint' => 'The case has not produced a recommendation that passed governance.',
                'action_type' => 'run_agent',
            ],
            'decision' => [
                'capability' => 'workflow',
                'label' => 'Review and approve',
                'hint' => 'A recommendation is waiting for your decision. Nothing happens until you make it.',
                'action_type' => 'approve',
                'recommendation_id' => $recommendation['id'] ?? null,
                'requires_approval' => true,
            ],
            'action' => [
                'capability' => 'workflow',
                'label' => 'Track the process',
                'hint' => 'Approved. Follow the steps as they complete.',
                'action_type' => 'track_workflow',
            ],
            'outcome' => [
                'capability' => 'workflow',
                'label' => 'Check the result',
                'hint' => 'The intervention is in place. Its effect is measured after the agreed period.',
                'action_type' => 'view_outcome',
            ],
            default => null,
        };
    }

    // ---------------------------------------------------------------- reads

    private function countSignals(string $entityKey, int|string $entityId, int $tenant): array
    {
        if (! Schema::hasTable('ai_signals')) {
            return ['total' => 0, 'open' => 0, 'highest_severity' => null];
        }

        $rows = DB::table('ai_signals')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->get(['severity', 'status']);

        $ladder = ['low' => 1, 'moderate' => 2, 'high' => 3, 'critical' => 4];
        $highest = null;

        foreach ($rows as $row) {
            if ($highest === null || ($ladder[$row->severity] ?? 0) > ($ladder[$highest] ?? 0)) {
                $highest = $row->severity;
            }
        }

        return [
            'total' => $rows->count(),
            'open' => $rows->where('status', 'open')->count(),
            'highest_severity' => $highest,
        ];
    }

    private function countEvidence(string $entityKey, int|string $entityId, int $tenant): array
    {
        if (! Schema::hasTable('ai_evidence')) {
            return ['total' => 0, 'verified' => 0, 'generated' => 0];
        }

        $rows = DB::table('ai_evidence')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->get(['verified', 'is_generated']);

        return [
            'total' => $rows->count(),
            // Only verified, non-generated evidence can support a claim, so that is
            // the number the strip reports.
            'verified' => $rows->where('verified', 1)->where('is_generated', 0)->count(),
            'generated' => $rows->where('is_generated', 1)->count(),
        ];
    }

    private function latestCase(string $entityKey, int|string $entityId, int $tenant): ?object
    {
        if (! Schema::hasTable('ai_cases')) {
            return null;
        }

        return DB::table('ai_cases')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first();
    }

    private function latestExplanation(int $caseId, int $tenant): ?object
    {
        if (! Schema::hasTable('ai_explanations')) {
            return null;
        }

        // Prefer one that passed; fall back to the latest so a refusal is visible
        // rather than looking like nothing was attempted.
        return DB::table('ai_explanations')
            ->where('case_id', $caseId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('governance_passed')
            ->orderByDesc('id')
            ->first();
    }

    private function latestRecommendation(string $entityKey, int|string $entityId, int $tenant): ?object
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return null;
        }

        return DB::table('ai_recommendations')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->whereNotIn('status', ['superseded', 'expired'])
            ->orderByDesc('id')
            ->first();
    }

    private function latestDecision(int $recommendationId, int $tenant): ?object
    {
        if (! Schema::hasTable('ai_decisions')) {
            return null;
        }

        return DB::table('ai_decisions')
            ->where('recommendation_id', $recommendationId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('decided_at')
            ->first();
    }

    private function latestWorkflowRun(string $entityKey, int|string $entityId, int $tenant): ?object
    {
        if (! Schema::hasTable('workflow_runs')) {
            return null;
        }

        return DB::table('workflow_runs')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first();
    }

    private function latestIntervention(string $entityKey, int|string $entityId, int $tenant): ?object
    {
        // Interventions are a K-12 student concept; other entity types have no
        // equivalent table yet, so the action stage falls back to the workflow run.
        if ($entityKey !== 'student' || ! Schema::hasTable('academic_interventions')) {
            return null;
        }

        return DB::table('academic_interventions')
            ->where('student_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first();
    }

    private function latestOutcome(string $entityKey, int|string $entityId, int $tenant): ?object
    {
        if (! Schema::hasTable('ai_outcomes')) {
            return null;
        }

        return DB::table('ai_outcomes')
            ->where('subject_entity_key', $entityKey)
            ->where('subject_id', $entityId)
            ->where('sub_institute_id', $tenant)
            ->orderByDesc('id')
            ->first();
    }

    private function outcomeSummary(object $outcome): string
    {
        return match ($outcome->status) {
            'improved' => sprintf('Improved by %s', $this->formatDelta($outcome->delta)),
            'worsened' => sprintf('Worsened by %s', $this->formatDelta($outcome->delta)),
            'unchanged' => 'No measurable change',
            'inconclusive' => 'Not enough data to judge',
            'measuring' => 'Baseline recorded, measuring',
            default => 'Scheduled for measurement',
        };
    }

    private function formatDelta(mixed $delta): string
    {
        return $delta === null ? 'an unknown amount' : number_format(abs((float) $delta), 1);
    }

    private function stageLabel(string $key): string
    {
        return match ($key) {
            'signal' => 'Risk detected',
            'evidence' => 'Evidence gathered',
            'case' => 'Case opened',
            'explanation' => 'Reason established',
            'recommendation' => 'Action proposed',
            'decision' => 'Approved by a person',
            'action' => 'Intervention created',
            'outcome' => 'Result measured',
            default => $this->humanize($key),
        };
    }

    private function humanize(string $value): string
    {
        $spaced = str_replace('_', ' ', trim($value));

        return $spaced === '' ? '' : ucfirst($spaced);
    }
}
