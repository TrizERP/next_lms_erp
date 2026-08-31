<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\StageContext;

/**
 * The fast path: a known intent routes the same way every time.
 *
 * This exists because the questions that matter most are the ones that must never be
 * creative. "Approve the recommendation" records a decision in a named person's account
 * and starts a workflow against a student's record; routing it through a model would
 * mean an approval that cannot be replayed and a route that can differ on a Tuesday.
 * Every intent here is a line of code somebody can read, and the trace names which one
 * matched.
 *
 * It plans only what it recognises. A question outside the intent registry gets null,
 * and the hybrid planner takes it to the model — which is the whole point of the split:
 * determinism where it is worth paying for, breadth everywhere else.
 */
class DeterministicPlanner implements Planner
{
    /**
     * The lookup tool every student-scoped route may need to resolve or hydrate a
     * person. Named as a candidate; whether it is actually called is stage 5's business.
     */
    private const STUDENT_LOOKUP = ['students.search'];

    /**
     * Intents whose answer is about one named student, and therefore need that student
     * resolved before anything else can be true of them.
     */
    /** The tools the admission confirmation flow needs, in the order it uses them. */
    private const ADMISSION_TOOLS = [
        'admissions.validateConfirmation',
        'admissions.updateEnquiry',
        'admissions.confirm',
    ];

    private const NEEDS_STUDENT = [
        'student_risk_explain',
        'evidence_inspect',
        'recommendation_advice',
        'approve_recommendation',
        'reject_recommendation',
        'workflow_status',
        'outcome_status',
    ];

    public function plan(StageContext $context): ?Plan
    {
        // A thread part-way through a task outranks whatever the sentence looks like on
        // its own. "Division B, quota general" classifies as nothing at all; read
        // against a pending admission it is the answer to the question just asked. Any
        // planner that classified first would strand every multi-turn flow on its
        // second turn.
        $continuation = $this->continuationPlan($context);

        if ($continuation !== null) {
            return $continuation;
        }

        $intent = $context->intent;

        if ($intent === null || $intent->isUnknown()) {
            return null;
        }

        $steps = $this->stepsFor($intent->key);

        if ($steps === null) {
            return null;
        }

        // A plan has to be executable by the module it is planned for.
        //
        // The classifier is estate-wide, so "who has low attendance?" matches the
        // academic-risk scan — a reasonable reading, and the intent it would want on a
        // student page. Routed at the attendance module, which has no agent, it planned
        // an agent run that could never happen and the turn reported four skipped stages
        // and no answer. Declining here hands the question to the model planner, which
        // will reach for attendance.overview and actually answer it.
        if ($this->routeFor($intent->key) === 'agent_runner' && ! $context->module->hasAgent()) {
            return null;
        }

        $needsStudent = in_array($intent->key, self::NEEDS_STUDENT, true)
            || $intent->slot('student_id') !== null
            || $intent->slot('student_name') !== null;

        $wanted = match ($intent->key) {
            'admission_confirm' => self::ADMISSION_TOOLS,
            'admission_enquiry_list' => ['admissions.listEnquiries'],
            default => $needsStudent ? self::STUDENT_LOOKUP : [],
        };

        // A module may only propose tools it is actually bound to. Without this, an
        // intent shared across modules would name a tool the module has no permission
        // to select, and stage 5 would have to refuse a plan its own planner wrote.
        $candidates = array_values(array_intersect($wanted, $context->module->mcpTools));

        $strategy = match (true) {
            $candidates === [] => 'domain_services_only',
            $intent->key === 'admission_confirm' => 'admissions_confirmation_flow',
            $intent->key === 'admission_enquiry_list' => 'mcp_read',
            default => 'mcp_student_resolution',
        };

        return new Plan(
            goal: $this->goalFor($intent->key, $intent->label),
            steps: $steps,
            source: Plan::SOURCE_DETERMINISTIC,
            route: $this->routeFor($intent->key),
            intentKey: $intent->key,
            candidateTools: $candidates,
            toolSelectionStrategy: $strategy,
            context: [
                'module' => $context->module->key,
                'confidence' => round($intent->confidence, 3),
                'matched_by' => 'intent registry',
            ],
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * A plan for continuing a task the thread is already part-way through.
     *
     * Returns null when there is nothing in flight, which is the ordinary case.
     */
    private function continuationPlan(StageContext $context): ?Plan
    {
        $pending = $context->thread['memory']['pending_action'] ?? null;

        if (! is_array($pending) || ($pending['kind'] ?? null) !== AdmissionsFlow::KIND) {
            return null;
        }

        $tools = array_values(array_intersect(self::ADMISSION_TOOLS, $context->module->mcpTools));

        if ($tools === []) {
            return null;
        }

        return new Plan(
            goal: 'Continue confirming the admission this conversation is part-way through.',
            steps: [
                new PlanStep('read_reply', 'Read the fields the user just supplied.'),
                new PlanStep(
                    'update',
                    'Write any supplied fields onto the enquiry.',
                    'admissions.updateEnquiry'
                ),
                new PlanStep(
                    'revalidate',
                    'Re-check what the admission still needs.',
                    'admissions.validateConfirmation'
                ),
                new PlanStep('report', 'Ask for what is still missing, or offer the confirmation.'),
            ],
            source: Plan::SOURCE_DETERMINISTIC,
            route: 'admissions_flow',
            intentKey: 'admission_confirm',
            candidateTools: $tools,
            toolSelectionStrategy: 'admissions_confirmation_flow',
            context: [
                'module' => $context->module->key,
                'matched_by' => 'a pending admission on this thread, which outranks the sentence',
                'pending' => $pending,
            ],
        );
    }

    private function routeFor(string $intentKey): string
    {
        return match ($intentKey) {
            'student_risk_scan' => 'agent_runner',
            'student_risk_explain' => 'stored_case_read',
            'evidence_inspect' => 'stored_evidence_read',
            'recommendation_advice' => 'stored_recommendation_read',
            'approve_recommendation', 'reject_recommendation' => 'decision_gate',
            'workflow_status' => 'workflow_read',
            'outcome_status' => 'outcome_read',
            'learning_effectiveness' => 'effectiveness_read',
            'admission_enquiry_list' => 'mcp_tools',
            'admission_confirm' => 'admissions_flow',
            default => 'conversation',
        };
    }

    private function goalFor(string $intentKey, string $label): string
    {
        return match ($intentKey) {
            'student_risk_scan' => 'Run the academic-risk agent, persist the governed findings, and surface any drafted recommendation.',
            'student_risk_explain' => 'Read the stored case, evidence and explanation for one student.',
            'evidence_inspect' => 'Read the verified evidence rows behind an existing case.',
            'recommendation_advice' => 'Read back the drafted recommendation and explain what it would do, without executing it.',
            'approve_recommendation' => 'Record a human approval and, when allowed, start the bound workflow.',
            'reject_recommendation' => 'Record a human rejection and stop the downstream workflow path.',
            'workflow_status' => 'Read the current workflow run and show which step it is waiting on.',
            'outcome_status' => 'Compare the recorded baseline against the latest measured outcome.',
            'learning_effectiveness' => 'Aggregate historical outcomes into effectiveness by action type.',
            'admission_enquiry_list' => 'List the admission enquiries on record and their state.',
            'admission_confirm' => 'Collect whatever the admission still needs, then put the confirmation to a person.',
            default => $label,
        };
    }

    /**
     * @return array<int, PlanStep>|null
     */
    private function stepsFor(string $intentKey): ?array
    {
        $steps = match ($intentKey) {
            // No hydration step here, deliberately.
            //
            // An earlier version listed "hydrate the highest-priority student through
            // Laravel MCP", and the trace then showed a plan promising an MCP call
            // beside a tool-selection stage reporting that nothing was selected. The
            // promise was unkeepable: which student is highest-priority is only known
            // after the agent runs, and the agent runs *after* the transport stage. A
            // plan must describe what the turn does, so the step is gone rather than
            // the contradiction being explained away. The student's name comes from the
            // case row, which is the record the decision is bound to anyway.
            'student_risk_scan' => [
                ['classify', 'Resolve the question into the academic-risk scan intent.'],
                ['agent', 'Run the module agent across the in-scope students.'],
                ['reason', 'Group signals into cases and compose governed explanations.'],
                ['recommend', 'Draft any recommendation that governance allows.'],
            ],
            'student_risk_explain' => [
                ['resolve_case', 'Identify the student or case the follow-up refers to.'],
                ['read_case', 'Load the stored case, evidence and governed explanation.'],
                ['report', 'Return the explanation and the evidence behind each claim.'],
            ],
            'evidence_inspect' => [
                ['resolve_case', 'Identify which case or student the user means.'],
                ['read_evidence', 'Load the verified evidence rows already linked to the case.'],
                ['report', 'Return evidence with provenance and any linked explanation.'],
            ],
            'recommendation_advice' => [
                ['resolve_case', 'Identify which case is being discussed.'],
                ['read_recommendation', 'Load the drafted recommendation and its explanation.'],
                ['report', 'Show the proposed action without executing it.'],
            ],
            'approve_recommendation', 'reject_recommendation' => [
                ['resolve_target', 'Identify the recommendation or workflow approval to decide on.'],
                ['decision', 'Record the human decision through the governed backend gate.'],
                ['workflow', 'Advance or halt the downstream workflow based on that decision.'],
            ],
            'workflow_status' => [
                ['resolve_workflow', 'Identify the workflow run tied to the case or recommendation.'],
                ['read_workflow', 'Load the workflow run and its current step states.'],
                ['report', 'Explain which downstream action has or has not happened yet.'],
            ],
            'outcome_status' => [
                ['resolve_case', 'Identify which intervention outcome to inspect.'],
                ['read_outcomes', 'Load the recorded baseline and latest measurement.'],
                ['report', 'Show whether the intervention improved the tracked metrics.'],
            ],
            'learning_effectiveness' => [
                ['read_outcomes', 'Load measured outcomes across prior interventions.'],
                ['aggregate', 'Summarise effectiveness by action type and status.'],
                ['report', 'Return what the system has learned from measured outcomes.'],
            ],
            'admission_enquiry_list' => [
                ['read_enquiries', 'Load the admission enquiries in scope.', 'admissions.listEnquiries'],
                ['report', 'Return the enquiries and which are still pending.'],
            ],
            'admission_confirm' => [
                ['resolve_enquiry', 'Identify which admission enquiry is being confirmed.'],
                ['validate', 'Check what the admission still needs.', 'admissions.validateConfirmation'],
                ['collect', 'Ask the user for anything missing, across as many turns as it takes.'],
                ['approve', 'Put the confirmation to a person — this creates a student enrolment.'],
            ],
            default => null,
        };

        if ($steps === null) {
            return null;
        }

        return array_map(
            static fn (array $step) => new PlanStep(
                id: $step[0],
                purpose: $step[1],
                tool: $step[2] ?? null,
            ),
            $steps
        );
    }
}
