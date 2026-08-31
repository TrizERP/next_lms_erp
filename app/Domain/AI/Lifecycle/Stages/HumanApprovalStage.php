<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Decisions\DecisionGate;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Stage 11 — the gate.
 *
 * Approving is the only thing in this pipeline that causes a student's record to change,
 * and it happens through DecisionGate and the workflow engine — never here. This stage
 * resolves *which* gate the user meant and hands the decision to the layer that owns it,
 * so every rule those layers enforce still applies to a sentence somebody typed.
 *
 * There are genuinely two gates and conflating them is a real bug. A recommendation
 * waits at the *recommendation* gate. A workflow that has already started can also be
 * waiting on a person, at its own `teacher_approval` step — a different record, a
 * different resolver, a different audit row. "Approve" should mean the obvious thing
 * when only one of them is open, and should ask when both are.
 *
 * On a turn that is not a decision, this stage reports `pending` rather than skipping:
 * something is waiting on a person, and that is the most important fact on the screen.
 */
class HumanApprovalStage implements LifecycleStage
{
    public function __construct(
        private readonly DecisionGate $decisions,
        private readonly RecommendationDrafter $recommendations,
        private readonly WorkflowEngine $workflows,
        private readonly AnswerComposer $compose,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::HumanApproval;
    }

    public function run(StageContext $context): StageOutcome
    {
        // The admission flow has a human gate of its own — a confirmation token that
        // only a person's "yes" spends. It is not the recommendation gate, but it is
        // unambiguously this stage's business, and reporting "no agent is registered for
        // admissions" while a person was in fact asked to approve a student enrolment
        // would be the trace describing a different turn than the one that happened.
        $admissions = $context->get('admissions_flow');

        if (is_array($admissions)) {
            return $this->admissionsGate($admissions);
        }

        if (! $context->module->hasAgent()) {
            return StageOutcome::notReached($context->module->whyNoDepth());
        }

        $decision = $this->decisionFrom($context);

        if ($decision === null) {
            return $this->waiting($context);
        }

        // A workflow step gate is resolved through the engine, not through DecisionGate.
        $approvalId = $context->intent?->slot('workflow_approval_id');

        if ($approvalId !== null) {
            return $this->resolveWorkflowStep($context, (int) $approvalId, $decision);
        }

        $recommendationId = $this->targetRecommendation($context, $decision);

        if ($recommendationId === null) {
            return $this->nothingToDecide($context, $decision);
        }

        return $this->record($context, (int) $recommendationId, $decision);
    }

    /**
     * @param  array<string, mixed>  $flow
     */
    private function admissionsGate(array $flow): StageOutcome
    {
        $enquiryId = $flow['enquiry_id'] ?? null;
        $base = [
            'gate' => 'App\\Mcp\\Tools\\AdmissionsConfirmTool',
            'enquiry_id' => $enquiryId,
            'why_it_gates' => 'Confirming creates a student enrolment, so the tool issues a '
                . 'confirmation token that only an explicit human "yes" spends.',
        ];

        return match ((string) ($flow['state'] ?? '')) {
            'ready' => StageOutcome::pending(
                sprintf('Admission #%s is waiting on a person to approve it.', $enquiryId),
                $base + ['blocking' => true],
                ['table' => 'admission_enquiry', 'ids' => array_filter([$enquiryId])]
            )->withComponent($base['gate']),

            // Records point at the enquiry, not at `mcp_confirmation_requests`. The token
            // row is transport for one turn, and naming it here sent a reader looking
            // for a table that is not deployed in every estate — the enquiry is the
            // record the decision was actually made about.
            'confirmed' => StageOutcome::ran(
                sprintf('A person approved admission #%s on this turn.', $enquiryId),
                $base + ['approved' => true],
                ['table' => 'admission_enquiry', 'ids' => array_filter([$enquiryId])]
            )->withComponent($base['gate']),

            'collecting' => StageOutcome::notReached(
                'The admission is still missing required fields, so it has not reached the '
                . 'approval gate yet.'
            ),

            'cancelled' => StageOutcome::skipped(
                'The user stopped before the approval gate; nothing was put to them to approve.'
            ),

            'already_confirmed' => StageOutcome::skipped(
                'This admission was approved on an earlier occasion; there was nothing to decide.'
            ),

            default => StageOutcome::notReached(
                'The admission flow stopped before reaching the approval gate.'
            ),
        };
    }

    // ---------------------------------------------------------------- deciding

    private function decisionFrom(StageContext $context): ?string
    {
        return match ($context->intent?->key) {
            'approve_recommendation' => 'approved',
            'reject_recommendation' => 'rejected',
            default => null,
        };
    }

    private function record(StageContext $context, int $recommendationId, string $decision): StageOutcome
    {
        $record = $this->recommendations->find($recommendationId, $context->scope);

        if ($record === null) {
            return StageOutcome::blocked(
                'That recommendation is not in your scope.',
                ['recommendation_id' => $recommendationId]
            )->halting('The decision could not be recorded, so nothing downstream ran.');
        }

        try {
            $result = $decision === 'approved'
                ? $this->decisions->approve($recommendationId, $context->scope, 'Approved from the AI console.')
                : $this->decisions->reject($recommendationId, $context->scope, 'Rejected from the AI console.');
        } catch (Throwable $exception) {
            return StageOutcome::blocked(
                $exception->getMessage(),
                ['recommendation_id' => $recommendationId, 'decision' => $decision]
            )->halting('The decision was refused, so nothing downstream ran.');
        }

        $context->set('decision', $result + ['decision' => $decision, 'recommendation' => $record]);

        $context->setHeadline($decision === 'approved'
            ? 'Approved — ' . ($record['title'] ?? 'the recommendation') . '.'
            : 'Rejected — ' . ($record['title'] ?? 'the recommendation') . '.');

        $context->link(['recommendation_id' => $recommendationId]);

        if ($decision === 'rejected') {
            $context->addSection($this->compose->text(
                'What happens now',
                'Nothing downstream runs. The case stays open and the evidence is retained, so the '
                . 'next run can raise it again if the situation worsens.'
            ));
        }

        return StageOutcome::ran(
            sprintf(
                'Recorded a human %s on recommendation #%d, by user %d.',
                $decision === 'approved' ? 'approval' : 'rejection',
                $recommendationId,
                $context->scope->userId
            ),
            [
                'gate' => DecisionGate::class,
                'decision' => $decision,
                'decision_id' => $result['decision_id'] ?? null,
                'recommendation_id' => $recommendationId,
                'status_before' => $record['status'] ?? null,
                'decided_by' => ['user_id' => $context->scope->userId, 'role' => $context->scope->role],
            ],
            ['table' => 'ai_decisions', 'ids' => array_filter([$result['decision_id'] ?? null])],
            ['api' => $this->prefix() . '/recommendations/' . $recommendationId . '/'
                . ($decision === 'approved' ? 'approve' : 'reject')]
        );
    }

    private function resolveWorkflowStep(StageContext $context, int $approvalId, string $decision): StageOutcome
    {
        try {
            $result = $this->workflows->resolveApproval(
                $approvalId,
                $decision,
                $context->scope,
                'Resolved from the AI console.'
            );
        } catch (Throwable $exception) {
            return StageOutcome::blocked(
                $exception->getMessage(),
                ['workflow_approval_id' => $approvalId]
            )->halting('The workflow step could not be resolved, so the run did not advance.');
        }

        $context->set('workflow_result', $result);
        $context->setHeadline($decision === 'approved'
            ? 'Workflow step approved.'
            : 'Workflow step rejected.');

        return StageOutcome::ran(
            sprintf('Resolved workflow approval #%d as %s.', $approvalId, $decision),
            [
                'gate' => 'workflow_approvals',
                'why_this_gate' => 'A workflow that has already started was waiting on a person at its '
                    . 'own step. That is a different record from the recommendation gate.',
                'workflow_approval_id' => $approvalId,
                'decision' => $decision,
                'run_id' => $result['run_id'] ?? null,
            ],
            ['table' => 'workflow_approvals', 'ids' => [$approvalId]],
            ['api' => $this->prefix() . '/approvals/' . $approvalId . '/resolve']
        );
    }

    // ---------------------------------------------------------------- resolving

    /**
     * Which recommendation this decision applies to.
     *
     * An explicit id wins. Otherwise, exactly one thing pending means the user can only
     * have meant that one; more than one means asking is the only honest option.
     */
    private function targetRecommendation(StageContext $context, string $decision): ?int
    {
        $explicit = $context->intent?->slot('recommendation_id');

        if ($explicit !== null) {
            return (int) $explicit;
        }

        $pending = $this->recommendations->pendingApproval($context->scope, 5);

        if (count($pending) === 1) {
            return (int) $pending[0]['id'];
        }

        if (count($pending) > 1) {
            $context->set('ambiguous_recommendations', $pending);
        }

        return null;
    }

    private function nothingToDecide(StageContext $context, string $decision): StageOutcome
    {
        $ambiguous = $context->get('ambiguous_recommendations', []);

        if ($ambiguous !== []) {
            $verb = $decision === 'approved' ? 'approve' : 'reject';

            $context->setHeadline(sprintf('Which one would you like to %s?', $verb));
            $context->addSection($this->compose->records(
                'Pending',
                array_map(static fn (array $row) => [
                    'title' => $row['title'] ?? '',
                    'meta' => ['Recommendation' => '#' . ($row['id'] ?? '?')],
                ], $ambiguous)
            ));

            foreach ($ambiguous as $row) {
                $context->addAction($this->compose->action(
                    'decide_' . ($row['id'] ?? '0'),
                    ucfirst($verb) . ': ' . ($row['title'] ?? ''),
                    $decision === 'approved' ? 'approve_recommendation' : 'reject_recommendation',
                    [
                        'recommendation_id' => $row['id'] ?? null,
                        'utterance' => ucfirst($verb) . ' recommendation ' . ($row['id'] ?? ''),
                    ]
                ));
            }

            return StageOutcome::pending(
                sprintf('%d recommendations are pending — the turn needs to be told which one.', count($ambiguous)),
                ['pending' => count($ambiguous)],
                ['table' => 'ai_recommendations', 'ids' => array_filter(array_column($ambiguous, 'id'))]
            )->halting('No single decision target was identified, so nothing was recorded or started.');
        }

        // Nothing at the recommendation gate — but a running workflow can still be parked
        // on a person, and "approve" should mean the obvious thing when one gate is open.
        $steps = $this->pendingWorkflowApprovals($context);

        if (count($steps) === 1) {
            return $this->resolveWorkflowStep($context, (int) $steps[0]['id'], $decision);
        }

        $context->setHeadline(count($steps) > 1
            ? sprintf('%d workflow steps are waiting — say which one, or approve it from the case.', count($steps))
            : 'There is nothing waiting for your approval.');

        $context->addSection($this->compose->text(
            'Next',
            'Run a risk scan first — that is what drafts a recommendation.'
        ));
        $context->suggestFollowUp('Which students are at academic risk?');

        return StageOutcome::skipped(
            'Nothing is waiting for a decision.',
            ['pending_recommendations' => 0, 'pending_workflow_steps' => count($steps)]
        )->halting('There was no decision to record, so no workflow was advanced.');
    }

    /**
     * Not a decision turn. Something may still be waiting — and if it is, that is the
     * headline fact, so it reports `pending` rather than skipping quietly.
     */
    private function waiting(StageContext $context): StageOutcome
    {
        $pending = $context->pendingRecommendation;

        if ($pending === null) {
            return StageOutcome::skipped(
                'No decision was asked for, and nothing is waiting at this gate.',
                []
            )->withNote('This gate opens when a recommendation is drafted.');
        }

        $context->suggestFollowUp('Approve the recommendation.');

        return StageOutcome::pending(
            sprintf(
                'Recommendation #%s is waiting on a person. Nothing downstream runs until it is decided.',
                $pending['id'] ?? '?'
            ),
            [
                'gate' => DecisionGate::class,
                'blocking' => true,
                'recommendation_id' => $pending['id'] ?? null,
                'why_it_waits' => 'The agent is licensed up to recommend. Creating the intervention is '
                    . 'a consequential action and needs a human decision first.',
            ],
            ['table' => 'ai_recommendations', 'ids' => array_filter([$pending['id'] ?? null])],
            ['api' => $this->prefix() . '/recommendations/' . ($pending['id'] ?? '{id}') . '/approve']
        );
    }

    /**
     * @return array<int, array{id:int, step_key:string, run_id:int}>
     */
    private function pendingWorkflowApprovals(StageContext $context): array
    {
        if (! Schema::hasTable('workflow_approvals')) {
            return [];
        }

        $scope = $context->scope;

        return DB::table('workflow_approvals')
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('status', 'pending')
            ->where(function ($query) use ($scope) {
                // Mine, my role's, or unassigned — the rule the approvals inbox uses.
                $query->where('assigned_to', $scope->userId)
                    ->orWhereNull('assigned_to')
                    ->orWhere('approver_role', $scope->role);
            })
            ->orderBy('id')
            ->get()
            ->map(static fn ($row) => [
                'id' => (int) $row->id,
                'step_key' => $row->step_key,
                'run_id' => (int) $row->run_id,
            ])
            ->all();
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
