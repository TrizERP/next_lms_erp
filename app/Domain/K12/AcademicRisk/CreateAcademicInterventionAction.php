<?php

namespace App\Domain\K12\AcademicRisk;

use App\Domain\Workflow\Actions\WorkflowAction;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Creates the intervention record — the moment the platform actually does something.
 *
 * Reached only through an `action` step, which the workflow engine will not run
 * without an approving human decision on the recommendation. By the time this class
 * executes, a named teacher has said yes and that yes is in `ai_decisions`.
 *
 * Idempotent by recommendation: re-running a step (a retry, a resumed run) finds the
 * existing intervention rather than creating a duplicate for the same student.
 */
class CreateAcademicInterventionAction implements WorkflowAction
{
    public function key(): string
    {
        return 'create_academic_intervention';
    }

    public function label(): string
    {
        return 'Create academic intervention';
    }

    public function execute(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): array {
        if (! Schema::hasTable('academic_interventions')) {
            throw new RuntimeException('The academic interventions table is not available.');
        }

        $payload = $this->payload($run, $state);
        $studentId = (int) ($payload['student_id'] ?? 0);

        if ($studentId <= 0) {
            throw new RuntimeException('This intervention has no student.');
        }

        // Re-running the same approved recommendation must not create a second record.
        if ($run->recommendationId !== null) {
            $existing = DB::table('academic_interventions')
                ->where('recommendation_id', $run->recommendationId)
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->first();

            if ($existing) {
                return [
                    'intervention_id' => (int) $existing->id,
                    'reference' => $existing->intervention_reference,
                    'student_id' => (int) $existing->student_id,
                    'already_existed' => true,
                ];
            }
        }

        $decisionId = $this->resolveDecisionId($run, $scope);
        $generated = $this->generatedActivity($state);
        $dueDays = (int) ($config['due_in_days'] ?? 14);

        $interventionId = (int) DB::table('academic_interventions')->insertGetId([
            'intervention_reference' => $this->nextReference(),
            'student_id' => $studentId,
            'student_name' => isset($payload['student_name'])
                ? mb_substr((string) $payload['student_name'], 0, 200)
                : null,
            'standard_id' => $payload['standard_id'] ?? null,
            'section_id' => $payload['section_id'] ?? null,
            'subject_id' => $payload['focus_subject_id'] ?? null,
            'case_id' => $run->caseId,
            'recommendation_id' => $run->recommendationId,
            'decision_id' => $decisionId,
            'workflow_run_id' => $run->runId,
            'intervention_type' => (string) ($config['intervention_type'] ?? 'academic_support'),
            'title' => mb_substr($this->title($payload, $config), 0, 300),
            'description' => $this->description($payload, $state),
            'rationale' => $state['explanation']['narrative'] ?? ($payload['rationale'] ?? null),
            'severity' => (string) ($payload['severity'] ?? 'moderate'),
            'activity_content' => $generated['content'],
            'activity_is_generated' => $generated['content'] !== null,
            'generation_output_id' => $generated['output_id'],
            'assigned_to' => $this->resolveAssignee($config, $state, $scope),
            'assigned_to_name' => $config['assigned_to_name'] ?? null,
            'created_by' => $scope->userId,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(max(1, $dueDays))->toDateString(),
            'status' => 'active',
            'progress_percent' => 0,
            'metadata' => json_encode([
                'workflow_key' => $run->workflowKey,
                'signals' => $payload['signals'] ?? null,
            ]),
            'sub_institute_id' => $scope->selectedInstituteId,
            'client_id' => $scope->clientId,
            'academic_year' => $scope->academicYear,
            'term_id' => $scope->termId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activityIds = $this->createActivities($interventionId, $generated, $payload, $dueDays, $scope);

        // The recommendation has now been acted on.
        if ($run->recommendationId !== null && Schema::hasTable('ai_recommendations')) {
            DB::table('ai_recommendations')
                ->where('id', $run->recommendationId)
                ->update(['status' => 'executed', 'updated_at' => now()]);
        }

        if ($run->caseId !== null && Schema::hasTable('ai_cases')) {
            DB::table('ai_cases')
                ->where('id', $run->caseId)
                ->update(['status' => 'in_progress', 'updated_at' => now()]);
        }

        return [
            'intervention_id' => $interventionId,
            'student_id' => $studentId,
            'activity_ids' => $activityIds,
            'already_existed' => false,
        ];
    }

    /**
     * The workflow payload the recommendation carried, merged with anything earlier
     * steps added to the run state.
     */
    private function payload(WorkflowRunContext $run, array $state): array
    {
        $payload = $run->input;

        if (isset($state['input']) && is_array($state['input'])) {
            $payload = array_merge($state['input'], $payload);
        }

        return $payload;
    }

    /**
     * @return array{content:string|null, output_id:int|null, structured:array|null}
     */
    private function generatedActivity(array $state): array
    {
        foreach ($state as $stepOutput) {
            if (is_array($stepOutput) && ! empty($stepOutput['is_generated']) && ! empty($stepOutput['content'])) {
                return [
                    'content' => (string) $stepOutput['content'],
                    'output_id' => $stepOutput['generation_output_id'] ?? null,
                    'structured' => is_array($stepOutput['structured'] ?? null) ? $stepOutput['structured'] : null,
                ];
            }
        }

        return ['content' => null, 'output_id' => null, 'structured' => null];
    }

    /**
     * Break generated structured output into individual assigned activities where
     * the generation produced them; otherwise create a single practice activity so
     * the intervention always has something concrete attached.
     *
     * @return array<int, int>
     */
    private function createActivities(
        int $interventionId,
        array $generated,
        array $payload,
        int $dueDays,
        McpRequestContext $scope
    ): array {
        if (! Schema::hasTable('academic_intervention_activities')) {
            return [];
        }

        $dueDate = now()->addDays(max(1, $dueDays))->toDateString();
        $activities = [];

        $structuredActivities = $generated['structured']['activities'] ?? null;

        if (is_array($structuredActivities) && $structuredActivities !== []) {
            foreach (array_slice($structuredActivities, 0, 10) as $activity) {
                if (! is_array($activity) || empty($activity['title'])) {
                    continue;
                }

                $activities[] = [
                    'title' => mb_substr((string) $activity['title'], 0, 300),
                    'instructions' => $activity['instructions'] ?? null,
                    'activity_type' => (string) ($activity['type'] ?? 'practice'),
                    'is_generated' => true,
                ];
            }
        }

        if ($activities === []) {
            $activities[] = [
                'title' => sprintf(
                    'Targeted practice%s',
                    ! empty($payload['focus_subject_name']) ? ' in ' . $payload['focus_subject_name'] : ''
                ),
                'instructions' => $generated['content'],
                'activity_type' => 'practice',
                'is_generated' => $generated['content'] !== null,
            ];
        }

        $ids = [];

        foreach ($activities as $activity) {
            $ids[] = (int) DB::table('academic_intervention_activities')->insertGetId([
                'intervention_id' => $interventionId,
                'activity_type' => $activity['activity_type'],
                'title' => $activity['title'],
                'instructions' => $activity['instructions'],
                'subject_id' => $payload['focus_subject_id'] ?? null,
                'is_generated' => $activity['is_generated'],
                'due_date' => $dueDate,
                'status' => 'assigned',
                'sub_institute_id' => $scope->selectedInstituteId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function resolveDecisionId(WorkflowRunContext $run, McpRequestContext $scope): ?int
    {
        if ($run->decisionId !== null) {
            return $run->decisionId;
        }

        if ($run->recommendationId === null || ! Schema::hasTable('ai_decisions')) {
            return null;
        }

        $id = DB::table('ai_decisions')
            ->where('recommendation_id', $run->recommendationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('decision', 'approved')
            ->orderByDesc('decided_at')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Default the assignee to the approving teacher — they said yes, so it is
     * reasonable that it lands with them unless the workflow says otherwise.
     */
    private function resolveAssignee(array $config, array $state, McpRequestContext $scope): ?int
    {
        if (isset($config['assigned_to']) && is_numeric($config['assigned_to'])) {
            return (int) $config['assigned_to'];
        }

        foreach ($state as $stepOutput) {
            if (is_array($stepOutput) && ! empty($stepOutput['decided_by']) && is_numeric($stepOutput['decided_by'])) {
                return (int) $stepOutput['decided_by'];
            }
        }

        return $scope->userId > 0 ? $scope->userId : null;
    }

    private function title(array $payload, array $config): string
    {
        if (! empty($config['title'])) {
            return (string) $config['title'];
        }

        $name = $payload['student_name'] ?? ('Student #' . ($payload['student_id'] ?? '?'));
        $subject = $payload['focus_subject_name'] ?? null;

        return $subject
            ? sprintf('Academic intervention for %s — %s', $name, $subject)
            : sprintf('Academic intervention for %s', $name);
    }

    private function description(array $payload, array $state): ?string
    {
        return $state['explanation']['narrative']
            ?? $payload['description']
            ?? null;
    }

    private function nextReference(): string
    {
        $prefix = sprintf('AIV-%d-', now()->year);

        $last = DB::table('academic_interventions')
            ->where('intervention_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('intervention_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
