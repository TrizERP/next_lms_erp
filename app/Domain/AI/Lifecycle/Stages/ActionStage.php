<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Outcomes\OutcomeTracker;
use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
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
        private readonly OutcomeTracker $outcomes,
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
            return $this->admissionsAction($admissions, $context);
        }

        $module = $context->module;

        if ($context->intent?->key === 'learning_effectiveness') {
            return $this->readLearning($context);
        }

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

        if ($context->intent?->key === 'outcome_status') {
            return $this->readOutcome($context);
        }

        if ($context->intent?->key === 'learning_effectiveness') {
            return $this->readLearning($context);
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
    private function admissionsAction(array $flow, StageContext $context): StageOutcome
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

        // Linked, not merely reported, because a confirmed admission is the one turn that
        // leaves the user somewhere to go next: the enrolment now exists and the module
        // that owns it can open on that exact record. The outcome below describes what
        // happened; these are the handles the panel needs to act on it, and only `links`
        // travels with the answer.
        $context->link([
            'enquiry_id' => $flow['enquiry_id'] ?? null,
            'student_id' => $studentId,
        ]);

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

    private function readOutcome(StageContext $context): StageOutcome
    {
        $resolved = $this->resolvedCase($context);

        if ($resolved === null) {
            return StageOutcome::notReached(
                'I need a student or case before I can read the measured outcome.'
            );
        }

        $caseId = (int) ($resolved['case']['case_id'] ?? $resolved['case']['id'] ?? 0);
        $studentId = (int) $resolved['student_id'];
        $studentName = (string) $resolved['student_name'];
        $runId = $this->latestRunFor($context, $caseId);
        $outcomes = $this->outcomes->forSubject('student', $studentId, $context->scope, 10);
        $forCase = array_values(array_filter(
            $outcomes,
            static fn (array $row) => (int) ($row['case_id'] ?? 0) === $caseId
        ));
        $outcomes = $forCase !== [] ? $forCase : $outcomes;

        if ($runId !== null) {
            $status = $this->workflows->status($runId, $context->scope);

            if ($status !== null) {
                $context->addSection($this->compose->steps('Workflow progress', $this->plannedSteps($status)));
                $this->reportCreatedIntervention($context, $runId, $status);
                $context->link(['workflow_run_id' => $runId]);
            }
        }

        if ($outcomes === []) {
            $context->setHeadline('Nothing is being measured for ' . $studentName . ' yet.');
            $context->addSection($this->compose->text(
                'Why',
                'An outcome is registered when a recommendation is approved: the recommendation names the '
                . 'metric, the direction, and how long to wait before measuring.'
            ));
            $context->suggestFollowUp('What should the teacher do?');

            return StageOutcome::notReached(
                sprintf('No outcome row exists yet for case #%d.', $caseId),
                ['case_id' => $caseId, 'student_id' => $studentId]
            );
        }

        $rows = array_map(fn (array $row) => [
            'label' => $row['metric_label'],
            'before' => $row['baseline_value'],
            'after' => $row['observed_value'],
            'delta' => $row['delta'],
            'target' => $row['target_value'],
            'status' => $row['status'],
            'measured_at' => $row['observed_at'],
            'measure_after' => $row['measure_after'],
        ], $outcomes);

        $measured = array_values(array_filter($rows, static fn (array $row) => $row['after'] !== null));
        $headline = $measured === []
            ? sprintf('Too early to tell - the first measurement for %s is due %s.', $studentName, $rows[0]['measure_after'] ?? 'soon')
            : sprintf(
                '%s: %s went from %s to %s (%s).',
                $studentName,
                $measured[0]['label'],
                $this->number($measured[0]['before']),
                $this->number($measured[0]['after']),
                $measured[0]['status']
            );

        $context->setHeadline($headline);
        $context->addSection($this->compose->comparison('Before and after', $rows));
        $context->addSection($this->compose->text(
            'How this is measured',
            'The baseline was captured at approval by the same resolver that reads the value now, '
            . 'so the comparison is like-for-like rather than two different calculations.'
        ));
        $context->suggestFollowUp('What has the system learned?');
        $context->link([
            'case_id' => $caseId,
            'student_id' => $studentId,
            'outcome_id' => $outcomes[0]['id'] ?? null,
            'workflow_run_id' => $runId,
        ]);

        return StageOutcome::ran(
            sprintf(
                '%d outcome%s tracked for case #%d; %s.',
                count($outcomes),
                count($outcomes) === 1 ? '' : 's',
                $caseId,
                implode(', ', array_map(
                    static fn (array $row) => $row['metric_label'] . ' is ' . $row['status'],
                    $outcomes
                ))
            ),
            [
                'case_id' => $caseId,
                'student_id' => $studentId,
                'outcomes' => $outcomes,
                'workflow_run_id' => $runId,
            ],
            ['table' => 'ai_outcomes', 'ids' => array_column($outcomes, 'id')],
            [
                'api' => $this->prefix() . '/outcomes?subject_entity_key=student&subject_id=' . $studentId,
                'sql' => 'select metric_label, baseline_value, observed_value, delta, status from ai_outcomes where subject_id = ' . $studentId,
            ]
        );
    }

    private function readLearning(StageContext $context): StageOutcome
    {
        $effectiveness = $this->outcomes->effectivenessByActionType($context->scope, AcademicRiskAgent::CASE_TYPE);

        if ($effectiveness === []) {
            $context->setHeadline('Nothing has been measured yet, so there is nothing learned yet.');
            $context->addSection($this->compose->text(
                'How the loop closes',
                'Each approved intervention registers an outcome with a metric and a horizon. When that '
                . 'horizon passes and the outcome is measured, it counts towards the effectiveness of its '
                . 'action type.'
            ));
            $context->suggestRiskJourney('Which students are at academic risk?');

            return StageOutcome::pending(
                'No academic intervention has been measured yet, so there is no effectiveness signal to feed back.',
                ['closes_when' => 'an approved intervention passes its measurement horizon and is measured'],
                ['table' => 'ai_outcomes', 'ids' => []],
                ['api' => 'GET ' . $this->prefix() . '/outcomes/effectiveness']
            );
        }

        $context->setHeadline('Effectiveness of academic interventions so far.');
        $context->addSection($this->compose->records('By action type', array_map(
            static fn (string $actionType, array $row) => [
                'title' => $actionType,
                'lines' => [sprintf(
                    'improved %d, unchanged %d, worsened %d',
                    $row['counts']['improved'] ?? 0,
                    $row['counts']['unchanged'] ?? 0,
                    $row['counts']['worsened'] ?? 0
                )],
                'meta' => array_filter([
                    'Measured' => $row['total'] ?? null,
                    'Improvement rate' => array_key_exists('improvement_rate', $row) && $row['improvement_rate'] !== null
                        ? round(((float) $row['improvement_rate']) * 100) . '%'
                        : null,
                ]),
            ],
            array_keys($effectiveness),
            $effectiveness
        )));
        $context->addSection($this->compose->text(
            'What the system does with this',
            'This is the feedback signal: an action type that keeps failing to move its metric is '
            . 'evidence against recommending it again, and the same measurement is what justifies '
            . 'recommending one that works.'
        ));
        $context->suggestRiskJourney('Which students are at academic risk?');

        return StageOutcome::ran(
            sprintf('Effectiveness known for %d action type(s).', count($effectiveness)),
            [
                'effectiveness' => $effectiveness,
                'how_it_feeds_back' => 'Measured outcomes are grouped by action type. That distribution is the '
                    . 'evidence for or against recommending the same action next time.',
            ],
            ['table' => 'ai_outcomes', 'ids' => []],
            ['api' => 'GET ' . $this->prefix() . '/outcomes/effectiveness']
        );
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
     * @return array{case:array<string, mixed>, student_id:int, student_name:string}|null
     */
    private function resolvedCase(StageContext $context): ?array
    {
        $resolved = $context->get('resolved_case');

        if (is_array($resolved) && isset($resolved['case'], $resolved['student_id'], $resolved['student_name'])) {
            return $resolved;
        }

        if ($context->focusCase === null) {
            return null;
        }

        $case = $context->focusCase;
        $studentId = (int) ($case['student_id'] ?? $case['subject_id'] ?? 0);

        if ($studentId <= 0) {
            return null;
        }

        return [
            'case' => $case,
            'student_id' => $studentId,
            'student_name' => (string) ($case['student_name'] ?? $case['subject_label'] ?? ('Student #' . $studentId)),
        ];
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
        $intervention = $this->reportCreatedIntervention($context, $runId, $status);

        if ($changed !== []) {
            $context->setHeadline($context->headline() ?? ($intervention !== null
                ? 'The intervention is active.'
                : 'The workflow completed a recorded action.'));

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

    /**
     * Render the business records the action actually wrote. Workflow step labels say
     * what was attempted; these scoped read-backs are what allow the answer to say an
     * intervention or activity exists without inventing a success message.
     *
     * @param  array<string, mixed>|null  $status
     * @return array<string, mixed>|null
     */
    private function reportCreatedIntervention(StageContext $context, int $runId, ?array $status): ?array
    {
        if (! Schema::hasTable('academic_interventions')) {
            return null;
        }

        $row = DB::table('academic_interventions')
            ->where('workflow_run_id', $runId)
            ->where('sub_institute_id', $context->scope->selectedInstituteId)
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return null;
        }

        $intervention = (array) $row;
        $interventionId = (int) ($intervention['id'] ?? 0);

        $context->link([
            'student_id' => $intervention['student_id'] ?? null,
            'student_name' => $intervention['student_name'] ?? null,
            'case_id' => $intervention['case_id'] ?? null,
            'recommendation_id' => $intervention['recommendation_id'] ?? null,
            'intervention_id' => $interventionId ?: null,
        ]);

        $context->addSection($this->compose->keyValues('Intervention record', array_filter([
            'Intervention' => ! empty($intervention['intervention_reference'])
                ? $intervention['intervention_reference']
                : ($interventionId > 0 ? '#' . $interventionId : null),
            'Student' => $intervention['student_name'] ?? null,
            'Status' => $intervention['status'] ?? null,
            'Subject ID' => isset($intervention['subject_id']) ? (string) $intervention['subject_id'] : null,
            'Starts' => $intervention['start_date'] ?? null,
            'Due' => $intervention['due_date'] ?? null,
            'Progress' => isset($intervention['progress_percent']) ? $intervention['progress_percent'] . '%' : null,
        ])));

        if ($interventionId > 0 && Schema::hasTable('academic_intervention_activities')) {
            $activities = DB::table('academic_intervention_activities')
                ->where('intervention_id', $interventionId)
                ->where('sub_institute_id', $context->scope->selectedInstituteId)
                ->orderBy('id')
                ->get()
                ->map(static fn ($activity) => [
                    'title' => $activity->title,
                    'badge' => ucfirst((string) $activity->status),
                    'lines' => array_values(array_filter([
                        $activity->due_date ? 'Due: ' . $activity->due_date : null,
                        $activity->is_generated ? 'Generated activity content' : 'Configured practice activity',
                    ])),
                    'meta' => ['Activity' => '#' . $activity->id],
                ])
                ->all();

            $context->addSection($this->compose->records('Assigned activities', $activities));
        }

        $notification = $this->notificationOutput($status);

        if ($notification !== null) {
            $context->addSection($this->compose->keyValues('Notification', [
                'Channel' => $notification['channel'] ?? null,
                'Audience' => $notification['audience'] ?? null,
                'Notification record' => isset($notification['notification_id']) && $notification['notification_id'] !== null
                    ? '#' . $notification['notification_id']
                    : null,
                'Delivered' => array_key_exists('delivered', $notification)
                    ? ($notification['delivered'] ? 'Yes' : 'No')
                    : null,
            ]));
        }

        $context->addSection($this->compose->keyValues('Database verification', [
            'Read-back query' => 'Successful',
            'Intervention ID' => $interventionId > 0 ? '#' . $interventionId : null,
            'Linked workflow run' => '#' . $runId,
            'Student link' => ! empty($intervention['student_id']) ? '#' . $intervention['student_id'] : null,
        ]));

        return $intervention;
    }

    /**
     * The notification handler records its exact output on the workflow step. This is
     * deliberately not inferred from the workflow definition: a configured channel is
     * not evidence that a notification row was written.
     *
     * @param  array<string, mixed>|null  $status
     * @return array<string, mixed>|null
     */
    private function notificationOutput(?array $status): ?array
    {
        foreach ((array) ($status['steps'] ?? []) as $step) {
            if (($step['step_key'] ?? null) !== 'notify_student' || ($step['status'] ?? null) !== 'completed') {
                continue;
            }

            return is_array($step['output'] ?? null) ? $step['output'] : null;
        }

        return null;
    }

    private function number(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
