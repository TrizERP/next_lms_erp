<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Stage 12 — the only stage that changes a record, and the one that most often should
 * not have.
 *
 * The distinction this stage protects is between the workflow *moving* and the action
 * *happening*. Approving a recommendation starts the run and parks it at its own
 * confirmation step: no student record has changed yet. Reporting that as a completed
 * action told readers the intervention existed when it did not, which is the most
 * consequential thing a trace can get wrong — so a started-but-unfinished run reports
 * `pending`, with a note saying exactly what it is waiting for.
 *
 * On an ordinary scan turn nothing here runs at all, and the reason is the whole design:
 * waiting on a human decision is the gate, not a gap.
 */
class ActionStage implements LifecycleStage
{
    /** Steps that only ever precede a real change. */
    private const PREPARATORY = ['generate_activity', 'teacher_approval'];

    public function __construct(
        private readonly WorkflowEngine $workflows,
        private readonly AnswerComposer $compose,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::Action;
    }

    public function run(StageContext $context): StageOutcome
    {
        // Confirming an admission writes a student record. It reaches that through its
        // own confirmable tool rather than through the workflow engine, but it is a real
        // change to a real row, which is exactly what this stage exists to report.
        $admissions = $context->get('admissions_flow');

        if (is_array($admissions)) {
            return $this->admissionsAction($admissions);
        }

        $module = $context->module;

        if (! $module->hasWorkflow()) {
            return StageOutcome::notReached($module->whyNoDepth());
        }

        $decision = $context->get('decision');

        if (is_array($decision) && ($decision['decision'] ?? null) === 'approved') {
            return $this->startWorkflow($context, $decision);
        }

        if (is_array($decision) && ($decision['decision'] ?? null) === 'rejected') {
            return StageOutcome::skipped(
                'The recommendation was rejected, so no workflow was started and no record changed.',
                ['decision' => 'rejected']
            );
        }

        $workflowResult = $context->get('workflow_result');

        if (is_array($workflowResult)) {
            return $this->reportRun($context, (int) ($workflowResult['run_id'] ?? 0));
        }

        if ($context->intent?->key === 'workflow_status') {
            return $this->readStatus($context);
        }

        if ($context->pendingRecommendation !== null) {
            return StageOutcome::notReached(
                'Waiting on the human decision above. This is the gate, not a gap — the agent is '
                . 'licensed to recommend and cannot create the intervention it proposed.'
            );
        }

        return StageOutcome::notReached(
            'Nothing reached this stage: no recommendation has been approved, so there was nothing '
            . 'to carry into a real change.'
        );
    }

    // ---------------------------------------------------------------- branches

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsAction(array $flow): StageOutcome
    {
        if ((string) ($flow['state'] ?? '') !== 'confirmed') {
            return StageOutcome::notReached(match ((string) ($flow['state'] ?? '')) {
                'ready' => 'Waiting on the person to approve. This is the gate, not a gap — the '
                    . 'enrolment is created only after they say yes.',
                'collecting' => 'The admission is still missing required fields, so nothing has '
                    . 'been written.',
                'cancelled' => 'The user stopped the flow, so no record was created.',
                'already_confirmed' => 'The enrolment already existed; this turn created nothing.',
                default => 'The admission flow stopped before anything could be written.',
            });
        }

        $data = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $studentId = $data['student_id'] ?? null;

        return StageOutcome::ran(
            sprintf(
                'Admission #%s became a student enrolment%s.',
                $flow['enquiry_id'] ?? '?',
                $studentId ? ' (student #' . $studentId . ')' : ''
            ),
            [
                'enquiry_id' => $flow['enquiry_id'] ?? null,
                'student_id' => $studentId,
                'enrollment_no' => $data['enrollment_no'] ?? null,
                'performed_by' => 'App\\Mcp\\Tools\\AdmissionsConfirmTool::executeConfirmed',
            ],
            ['table' => 'tblstudent', 'ids' => array_filter([$studentId])],
            ['sql' => 'select * from tblstudent where id = ' . ((int) $studentId)]
        )->withComponent('App\\Mcp\\Tools\\AdmissionsConfirmTool');
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function startWorkflow(StageContext $context, array $decision): StageOutcome
    {
        $recommendation = is_array($decision['recommendation'] ?? null) ? $decision['recommendation'] : [];
        $workflowKey = $recommendation['workflow_key'] ?? $context->module->workflowKey;

        if (! is_string($workflowKey) || $workflowKey === '') {
            return StageOutcome::skipped(
                'The approval was recorded, but this recommendation binds no workflow.',
                ['recommendation_id' => $recommendation['id'] ?? null]
            )->withNote('Nothing downstream can run without a bound workflow, so no record changed.');
        }

        try {
            $payload = is_array($recommendation['workflow_payload'] ?? null)
                ? $recommendation['workflow_payload']
                : [];

            $run = $this->workflows->start($workflowKey, $context->scope, $payload, [
                'trigger_type' => 'recommendation_approved',
                'recommendation_id' => $recommendation['id'] ?? null,
                'decision_id' => $decision['decision_id'] ?? null,
                'case_id' => $recommendation['case_id'] ?? null,
                'subject_entity_key' => $recommendation['subject_entity_key'] ?? null,
                'subject_id' => $recommendation['subject_id'] ?? null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return StageOutcome::blocked(
                'The decision was recorded, but the workflow could not be started: ' . $exception->getMessage(),
                ['workflow_key' => $workflowKey]
            )->withNote(
                'The approval stands. Nothing was created, so the run can be started again once the '
                . 'cause is fixed.'
            );
        }

        return $this->reportRun($context, (int) ($run['run_id'] ?? 0), $workflowKey, $run);
    }

    /**
     * "What happened after I approved?"
     *
     * The run is found from the case rather than named in the sentence, because nobody
     * types a workflow run id. An approved recommendation with no run behind it is a
     * real and alarming state — the decision stands but nothing carried it — so it is
     * reported as a refusal with the reassurance that re-approving is safe, rather than
     * as an absence.
     */
    private function readStatus(StageContext $context): StageOutcome
    {
        $caseId = (int) ($context->focusCase['case_id'] ?? $context->focusCase['id'] ?? 0);
        $runId = $this->latestRunFor($context, $caseId);

        if ($runId === null) {
            $approved = $context->get('approved_recommendations', []);

            if ($approved !== []) {
                $context->setHeadline('The recommendation was approved, but the workflow did not start.');
                $context->addSection($this->compose->text(
                    'What to do',
                    'Re-approving is safe — the decision is already recorded and will not be duplicated.'
                ));

                return StageOutcome::blocked(
                    'A recommendation on this case is approved, but no workflow run exists for it.',
                    ['case_id' => $caseId]
                );
            }

            return StageOutcome::notReached(
                'Nothing on this case has been approved yet, so the workflow never started and no '
                . 'record has changed.'
            );
        }

        return $this->reportRun($context, $runId);
    }

    private function latestRunFor(StageContext $context, int $caseId): ?int
    {
        $pinned = $context->payload('workflow_run_id');

        if ($pinned !== null) {
            return $pinned;
        }

        if ($caseId <= 0 || ! Schema::hasTable('workflow_runs')) {
            return null;
        }

        $run = DB::table('workflow_runs')
            ->where('sub_institute_id', $context->scope->selectedInstituteId)
            ->where('case_id', $caseId)
            ->orderByDesc('id')
            ->first();

        return $run ? (int) $run->id : null;
    }

    /**
     * Report a run, distinguishing "advanced" from "acted".
     *
     * @param  array<string, mixed>|null  $startResult
     */
    private function reportRun(
        StageContext $context,
        int $runId,
        ?string $workflowKey = null,
        ?array $startResult = null
    ): StageOutcome {
        if ($runId <= 0) {
            return StageOutcome::blocked(
                'The workflow did not start.',
                ['workflow_key' => $workflowKey, 'result' => $startResult]
            );
        }

        $status = $this->workflows->status($runId, $context->scope);
        $steps = $this->plannedSteps($status);

        $context->addSection($this->compose->steps('What the workflow is doing', $steps));
        $context->link(['workflow_run_id' => $runId]);

        $changed = $this->completedRealSteps($steps);
        $current = $status['current_step_key'] ?? null;
        $records = ['table' => 'workflow_runs', 'ids' => [$runId]];
        $verify = ['api' => $this->prefix() . '/workflow-runs/' . $runId];

        if ($changed !== []) {
            $context->setHeadline($context->headline() ?? 'The intervention has been created.');

            return StageOutcome::ran(
                sprintf(
                    'Workflow run #%d changed %d real record%s: %s.',
                    $runId,
                    count($changed),
                    count($changed) === 1 ? '' : 's',
                    implode(', ', $changed)
                ),
                [
                    'run_id' => $runId,
                    'workflow_key' => $workflowKey ?? ($status['workflow_key'] ?? null),
                    'status' => $status['status'] ?? null,
                    'steps' => $steps,
                    'changed_by_steps' => $changed,
                ],
                $records,
                $verify
            );
        }

        // The run moved but nothing has changed yet. This is the honest, common case
        // immediately after an approval, and calling it "done" would be a lie about a
        // child's record.
        return StageOutcome::pending(
            sprintf(
                'Workflow run #%d started and is waiting at "%s".',
                $runId,
                $current ?? 'its next step'
            ),
            [
                'run_id' => $runId,
                'workflow_key' => $workflowKey ?? ($status['workflow_key'] ?? null),
                'status' => $status['status'] ?? null,
                'current_step' => $current,
                'steps' => $steps,
            ],
            $records,
            $verify
        )->withNote(
            'The workflow advanced, but no record has changed yet — this stage completes when the run '
            . 'reaches the step that writes one.'
        );
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Steps as the console renders them: the version's plan, marked with what happened.
     *
     * @param  array<string, mixed>|null  $status
     * @return array<int, array<string, mixed>>
     */
    private function plannedSteps(?array $status): array
    {
        if ($status === null) {
            return [];
        }

        return array_map(static fn (array $step) => [
            'step_key' => $step['step_key'] ?? null,
            'label' => $step['label'] ?? ucfirst(str_replace('_', ' ', (string) ($step['step_key'] ?? ''))),
            'type' => $step['step_type'] ?? null,
            'status' => $step['status'] ?? 'pending',
            'finished_at' => $step['finished_at'] ?? null,
            'is_current' => ($status['current_step_key'] ?? null) === ($step['step_key'] ?? null),
        ], is_array($status['steps'] ?? null) ? $status['steps'] : []);
    }

    /**
     * Completed steps that actually wrote something.
     *
     * Generating a draft and collecting an approval are real work, but neither changes a
     * student's record — so neither may count toward this stage completing.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, string>
     */
    private function completedRealSteps(array $steps): array
    {
        $changed = [];

        foreach ($steps as $step) {
            $key = (string) ($step['step_key'] ?? '');

            if (($step['status'] ?? '') !== 'completed' || in_array($key, self::PREPARATORY, true)) {
                continue;
            }

            $changed[] = (string) ($step['label'] ?? $key);
        }

        return $changed;
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
