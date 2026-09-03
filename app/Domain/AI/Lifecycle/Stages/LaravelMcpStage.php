<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\Plan\Plan;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;

/**
 * Stage 6 — the transport, and the stage that has to be able to say "I was refused".
 *
 * Tools carry their own role gates. When one refuses, the turn falls back to a scoped
 * domain service so the answer survives — a lookup gate must not become an answer gate —
 * but this stage still reports that Laravel MCP was reached and turned the call down.
 * Reporting a governance decision as "no call was needed" would hide the most
 * interesting thing that happened.
 *
 * Two routes arrive here and they source their arguments differently. A deterministic
 * plan resolves arguments from the intent's own slots, because the classifier already
 * extracted the student or the case. A model plan carries arguments the model filled in,
 * which the tool's own validator then accepts or rejects — the planner is never trusted
 * to have got them right, only to have proposed them.
 */
class LaravelMcpStage implements LifecycleStage
{
    public function __construct(
        private readonly McpToolCaller $caller,
        private readonly AdmissionsFlow $admissions,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::LaravelMcp;
    }

    public function run(StageContext $context): StageOutcome
    {
        $plan = $context->plan;

        if ($context->selectedTools === [] || $plan === null) {
            return StageOutcome::skipped(
                'No Laravel MCP call was needed for this turn.',
                [
                    'strategy' => $plan?->toolSelectionStrategy ?? 'domain_services_only',
                    'module_bound_tools' => $context->module->mcpTools,
                ]
            )->withNote(
                'This turn stayed on the conversation and agent path, so the MCP transport was '
                . 'available but not invoked.'
            );
        }

        // Dispatch on the route rather than on which planner wrote the plan. The two
        // used to be conflated — "was this the model?" stood in for "does this plan name
        // calls to make?" — which meant a deterministic plan that did name tool calls
        // silently took the student-resolution path and never made them.
        match ($plan->route) {
            'admissions_flow' => $this->runAdmissionsFlow($context),
            'mcp_tools' => $this->runPlannedSteps($context),
            default => $plan->source === Plan::SOURCE_LLM
                ? $this->runPlannedSteps($context)
                : $this->resolveSubject($context),
        };

        return $this->report($context);
    }

    /**
     * The admission confirmation flow, which may span several turns.
     *
     * The stage does not know the shape of that conversation — it hands the turn to the
     * flow and reports the calls the flow made. Everything the answer needs is left on
     * the context for the reasoning stage to say out loud.
     */
    private function runAdmissionsFlow(StageContext $context): void
    {
        $pending = $this->admissions->pending($context->thread['memory'] ?? []);

        if ($pending !== null && $this->admissions->looksLikeCancel($context->question)) {
            $context->set('admissions_flow', [
                'state' => 'cancelled',
                'enquiry_id' => $pending['enquiry_id'] ?? null,
                'message' => 'Stopped. Nothing was confirmed and nothing was changed.',
                'missing' => [],
                'supplied' => [],
                'pending' => null,
            ]);

            return;
        }

        $result = $pending !== null
            ? $this->admissions->advance($context, $pending)
            : $this->admissions->start($context, (int) ($context->intent?->slot('enquiry_id') ?? 0));

        $context->set('admissions_flow', $result);
    }

    // ---------------------------------------------------------------- routes

    /**
     * A model plan names its own calls and arguments; run them in declared order.
     *
     * Results are kept on the context under the step id so a later step's `depends_on`
     * has something to refer to and the answer stage can cite which step produced which
     * figure.
     */
    private function runPlannedSteps(StageContext $context): void
    {
        $results = [];

        foreach ($context->plan->steps as $step) {
            if (! $step->isToolCall() || ! in_array($step->tool, $context->selectedTools, true)) {
                continue;
            }

            $results[$step->id] = $this->caller->call(
                $context,
                $step->tool,
                $step->arguments,
                $step->purpose
            );
        }

        $context->set('mcp_step_results', $results);
    }

    /**
     * The deterministic route needs one thing from MCP: who the question is about.
     *
     * A decision is recorded against a named student, not against an id, and the name a
     * teacher reads in the answer comes from this call.
     */
    private function resolveSubject(StageContext $context): void
    {
        $intent = $context->intent;

        if ($intent === null || ! in_array('students.search', $context->selectedTools, true)) {
            return;
        }

        $studentId = $intent->slot('student_id');

        if ($studentId !== null) {
            $student = $this->caller->firstStudent(
                $context,
                ['student_id' => (int) $studentId, 'limit' => 1, 'active_only' => false],
                'Hydrate the student this turn is about.'
            );

            if ($student !== null) {
                $context->set('resolved_student', $student);
            }

            return;
        }

        $name = $intent->slot('student_name');

        if ($name === null) {
            return;
        }

        $matches = $this->caller->searchStudents(
            $context,
            ['query' => $name, 'limit' => 5, 'active_only' => false],
            'Resolve the student named in the sentence.'
        );

        if (count($matches) === 1) {
            $context->set('resolved_student', $matches[0]);

            return;
        }

        if (count($matches) > 1) {
            // Ambiguity is a real finding, not an error. The reasoning stage turns this
            // into a question back to the user rather than picking one at random.
            $context->set('ambiguous_students', $matches);
        }
    }

    // ---------------------------------------------------------------- report

    private function report(StageContext $context): StageOutcome
    {
        $calls = $context->toolCalls();

        if ($calls === []) {
            return StageOutcome::skipped(
                'The selected tool was not needed once the turn resolved.',
                ['selected_tools' => $context->selectedTools]
            )->withNote(
                'A selection is not an obligation. This route found what it needed without calling.'
            );
        }

        $blocked = array_values(array_filter(
            $calls,
            static fn (array $call) => in_array($call['status'] ?? null, ['blocked', 'unavailable'], true)
        ));
        $completed = count($calls) - count($blocked);

        $data = [
            'calls' => $calls,
            'tools' => $context->executedTools(),
            'completed' => $completed,
            'blocked' => count($blocked),
        ];

        if ($completed === 0) {
            return StageOutcome::blocked(
                sprintf(
                    'Laravel MCP refused %d tool call%s.',
                    count($blocked),
                    count($blocked) === 1 ? '' : 's'
                ),
                $data
            )->withNote(
                trim((string) ($blocked[0]['error'] ?? '')) ?: "The caller is outside the tool's allowed roles."
            );
        }

        return StageOutcome::ran(
            sprintf(
                'Laravel MCP executed %d tool call%s.',
                $completed,
                $completed === 1 ? '' : 's'
            ),
            $data,
            [],
            ['api' => 'POST /api/mcp/tools/call']
        )->withNote($blocked === [] ? null : sprintf(
            "%d further call%s refused by the tool's role gate; the turn fell back to the scoped resolver.",
            count($blocked),
            count($blocked) === 1 ? ' was' : 's were'
        ));
    }
}
