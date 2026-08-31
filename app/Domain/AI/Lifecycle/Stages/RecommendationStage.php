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

        $pending = $this->firstPending($drafted);
        $context->pendingRecommendation = $pending;
        $target = $pending ?? $drafted[0];

        // The action stage needs this to tell two very different silences apart: a case
        // with nothing approved has simply not reached stage 12, while a case with an
        // approval and no workflow run behind it is a genuine fault worth alarming about.
        $context->set('approved_recommendations', array_values(array_filter(
            $drafted,
            static fn (array $row) => ($row['status'] ?? '') === 'approved'
        )));

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
