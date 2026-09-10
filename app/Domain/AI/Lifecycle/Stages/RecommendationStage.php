<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\Support\CaseResolver;
use App\Domain\AI\Recommendations\RecommendationDrafter;

/**
 * Stage 10 — what the system proposes, and the ceiling it stops at.
 *
 * The agent may draft the intervention. It may not create it. That is the verb ceiling
 * in its manifest, and this stage is where it becomes visible: a recommendation is left
 * sitting at `pending_approval` with a person's name on the next step, however obvious
 * the right answer looks.
 *
 * A module with no agent cannot reach this stage at all, and says so in words that name
 * the missing piece rather than shrugging. That is the difference between a ladder that
 * is honestly twelve rungs for every module and one that quietly changes length.
 */
class RecommendationStage implements LifecycleStage
{
    public function __construct(
        private readonly RecommendationDrafter $recommendations,
        private readonly CaseResolver $caseResolver,
        private readonly AnswerComposer $compose,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::Recommendation;
    }

    public function run(StageContext $context): StageOutcome
    {
        $module = $context->module;

        if (! $module->hasAgent()) {
            return StageOutcome::notReached($module->whyNoDepth());
        }

        // A scan can return several students, and a recommendation is consequential, so
        // the agent's first ranked row must not become the thing a bare "approve the
        // recommendation" acts on. What follows shows that recommendation without
        // arming it: the section names the student, and the buttons carry the
        // recommendation id explicitly, so a decision is only ever taken against a
        // record the user could see they were choosing. `pendingRecommendation` stays
        // null, which is what HumanApprovalStage reads, so an unqualified approval
        // still asks which student is meant.
        if ($context->intent?->key === 'student_risk_scan' && count($context->cases) > 1) {
            return $this->forRankedRiskScan($context);
        }

        $resolved = $this->caseResolver->resolve($context);

        if ($resolved === null) {
            return StageOutcome::skipped(
                'No case was in play, so there was nothing to recommend against.',
                []
            )->withNote('A recommendation is always bound to a case; without one there is nothing to bind.');
        }

        $caseId = (int) ($resolved['case']['case_id'] ?? $resolved['case']['id'] ?? 0);
        $drafted = $caseId > 0 ? $this->recommendations->forCase($caseId, $context->scope) : [];

        if ($drafted === []) {
            return StageOutcome::skipped(
                sprintf('No recommendation is drafted on case #%d.', $caseId),
                ['case_id' => $caseId]
            )->withNote(
                'A recommendation is only drafted when the explanation passes governance. If the '
                . 'reasoning stage above refused, this is why.'
            );
        }

        $active = array_values(array_filter(
            $drafted,
            static fn (array $row) => in_array(($row['status'] ?? ''), ['approved', 'executed'], true)
        ));
        $pending = $active === [] ? $this->firstPending($drafted) : null;
        $context->pendingRecommendation = $pending;
        $target = $active[0] ?? $pending ?? $drafted[0];

        // The action stage needs this to tell two very different silences apart: a case
        // with nothing approved has simply not reached stage 12, while a case with an
        // approval and no workflow run behind it is a genuine fault worth alarming about.
        $context->set('approved_recommendations', array_values(array_filter(
            $drafted,
            static fn (array $row) => in_array(($row['status'] ?? ''), ['approved', 'executed'], true)
        )));

        if (in_array($context->intent?->key, ['workflow_status', 'outcome_status'], true)) {
            $context->link(['recommendation_id' => $target['id'] ?? null]);

            return StageOutcome::ran(
                'Read the recommendation state needed to verify the existing workflow; no new recommendation was drafted.',
                ['recommendation_id' => $target['id'] ?? null, 'status' => $target['status'] ?? null],
                ['table' => 'ai_recommendations', 'ids' => array_filter([$target['id'] ?? null])]
            );
        }

        $this->describe($context, $target, $pending !== null);

        $context->link([
            'recommendation_id' => $target['id'] ?? null,
        ]);

        return StageOutcome::ran(
            $pending !== null
                ? sprintf(
                    '%d intervention%s drafted; "%s" is waiting for a human decision.',
                    count($drafted),
                    count($drafted) === 1 ? '' : 's',
                    $pending['title'] ?? 'an action'
                )
                : sprintf(
                    '%d recommendation%s on this case, none of them awaiting a decision.',
                    count($drafted),
                    count($drafted) === 1 ? '' : 's'
                ),
            [
                'action_type' => $target['action_type'] ?? null,
                'status' => $target['status'] ?? null,
                'rule' => 'The agent may draft the action. It may not perform it — that needs an '
                    . 'approval and then the workflow.',
                'bound_workflow' => $target['workflow_key'] ?? $module->workflowKey,
                'items' => array_map(static fn (array $row) => [
                    'recommendation_id' => $row['id'] ?? null,
                    'title' => $row['title'] ?? null,
                    'status' => $row['status'] ?? null,
                    'requires_approval' => ($row['status'] ?? '') === 'pending_approval',
                ], $drafted),
            ],
            ['table' => 'ai_recommendations', 'ids' => array_filter(array_column($drafted, 'id'))],
            [
                'api' => $this->prefix() . '/recommendations/pending',
                'sql' => 'select id, title, status from ai_recommendations where case_id = ' . $caseId,
            ]
        );
    }

    /**
     * Put the proposal in the answer, with the commitment behind it.
     *
     * @param  array<string, mixed>  $target
     */
    private function describe(StageContext $context, array $target, bool $isPending): void
    {
        $eso = is_array($target['eso_binding'] ?? null) ? $target['eso_binding'] : [];

        $context->addSection($this->compose->text(
            'Recommended action',
            (string) ($target['title'] ?? '') . ($isPending ? ' — waiting for your approval.' : '')
        ));

        if (! empty($target['body'])) {
            $context->addSection($this->compose->text('What this does', (string) $target['body']));
        }

        $context->addSection($this->compose->keyValues('The commitment behind it', array_filter([
            'Objective' => $eso['objective'] ?? null,
            'Strategy' => $eso['strategy'] ?? null,
            'Measured by' => $eso['outcome']['metric_label'] ?? null,
            'Direction' => $eso['outcome']['direction'] ?? null,
            'Checked after' => isset($eso['outcome']['horizon_days'])
                ? $eso['outcome']['horizon_days'] . ' days'
                : null,
        ])));

        if (! $isPending) {
            $status = (string) ($target['status'] ?? '');

            if (in_array($status, ['approved', 'executed'], true)) {
                $context->addSection($this->compose->text(
                    'Action status',
                    'This recommendation has already been approved and is no longer eligible for a second approval.'
                ));
                $context->addAction($this->compose->action(
                    'view_intervention_progress',
                    'View intervention progress',
                    'workflow_status',
                    [
                        'case_id' => $target['case_id'] ?? null,
                        'student_id' => $target['subject_id'] ?? null,
                        'utterance' => 'What happened after approval?',
                    ]
                ));
            }

            return;
        }

        $recommendationId = $target['id'] ?? null;

        $context->addAction($this->compose->action(
            'approve',
            'Approve: ' . ($target['title'] ?? 'this action'),
            'approve_recommendation',
            ['recommendation_id' => $recommendationId, 'utterance' => 'Approve the recommendation.'],
            'primary'
        ));

        $context->addAction($this->compose->action(
            'reject',
            'Reject',
            'reject_recommendation',
            ['recommendation_id' => $recommendationId, 'utterance' => 'Reject the recommendation.'],
            'danger'
        ));
    }

    /**
     * The proposal for whoever ranked first, shown but not armed.
     *
     * The scan has already argued which case is most urgent, and withholding what it
     * proposes for that case made the answer stop one step short of being useful: the
     * reader could see who needed help and not what the system would do about it.
     *
     * What is *not* done here is the point. `pendingRecommendation` is left null, so
     * HumanApprovalStage still has nothing to act on and an unqualified "approve the
     * recommendation" asks which student is meant. The buttons name the student and
     * carry the recommendation id, so a decision taken from this answer is taken
     * against a record the user could see they were choosing — which is the distinction
     * that matters. Reading a proposal is not deciding on it.
     */
    private function forRankedRiskScan(StageContext $context): StageOutcome
    {
        $top = $context->cases[0] ?? null;
        $target = is_array($top['recommendation'] ?? null) ? $top['recommendation'] : null;

        $caseIds = array_values(array_filter(array_map(
            static fn (array $case) => $case['case_id'] ?? $case['id'] ?? null,
            $context->cases
        )));

        if ($target === null || ($target['id'] ?? null) === null) {
            return StageOutcome::skipped(
                'The scan returned multiple cases and the highest-priority one has no drafted recommendation.',
                ['case_ids' => $caseIds]
            );
        }

        $student = (string) ($top['student_name'] ?? 'this student');
        $isPending = ($target['status'] ?? '') === 'pending_approval';

        $context->addSection($this->compose->text(
            'Recommended action',
            sprintf(
                '%s for %s%s',
                (string) ($target['title'] ?? ''),
                $student,
                $isPending ? ' — waiting for your approval.' : '.'
            )
        ));

        if ($isPending) {
            $context->addAction($this->compose->action(
                'approve',
                'Approve: ' . ($target['title'] ?? 'this action') . ' (' . $student . ')',
                'approve_recommendation',
                [
                    'recommendation_id' => $target['id'],
                    'case_id' => $top['case_id'] ?? null,
                    'student_id' => $top['student_id'] ?? null,
                    'utterance' => sprintf('Approve the recommendation for %s.', $student),
                ],
                'primary'
            ));

            $context->addAction($this->compose->action(
                'reject',
                'Reject (' . $student . ')',
                'reject_recommendation',
                [
                    'recommendation_id' => $target['id'],
                    'case_id' => $top['case_id'] ?? null,
                    'student_id' => $top['student_id'] ?? null,
                    'utterance' => sprintf('Reject the recommendation for %s.', $student),
                ],
                'danger'
            ));
        }

        return StageOutcome::ran(
            sprintf(
                'Showed the recommendation drafted for the highest-priority case of %d, without binding it for approval.',
                count($caseIds)
            ),
            [
                'case_ids' => $caseIds,
                'shown_recommendation_id' => $target['id'],
                'armed_for_approval' => false,
                'rule' => 'A recommendation is displayed for the top case, but only a person selecting one '
                    . 'student binds it for approval.',
            ],
            ['table' => 'ai_recommendations', 'ids' => [$target['id']]]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $recommendations
     * @return array<string, mixed>|null
     */
    private function firstPending(array $recommendations): ?array
    {
        foreach ($recommendations as $row) {
            if (($row['status'] ?? '') === 'pending_approval') {
                return $row;
            }
        }

        return null;
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
