<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Agents\AgentRunner;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * Stage 3 — the module's agent, when the plan calls for one.
 *
 * The agent is the most expensive thing this pipeline does and the only thing that reads
 * a whole cohort, so it runs only when a plan asked for it. A follow-up question about a
 * case that already exists must not re-analyse the school to answer it.
 *
 * This stage is also where the pipeline's most consequential distinction lives: **a
 * crashed run is not an empty result.** A failed run returns no cases, and reporting
 * that as "nothing crossed its trigger" would be a confident statement about a school's
 * children made on the strength of an exception. The two outcomes get different words,
 * and a failure halts the turn so no downstream stage can imply the analysis completed.
 *
 * The verb ceiling is the other thing worth reading here. The manifest licenses this
 * agent up to *recommend*. It may draft an intervention; it may not create one. Stage 11
 * is a person, and stage 12 waits on them — by design, not by omission.
 */
class AgentStage implements LifecycleStage
{
    public function __construct(private readonly AgentRunner $agents)
    {
    }

    public function key(): StageKey
    {
        return StageKey::Agent;
    }

    public function run(StageContext $context): StageOutcome
    {
        $module = $context->module;
        $plan = $context->plan;

        if (! $module->hasAgent()) {
            return StageOutcome::skipped(
                sprintf('No agent ran — the %s module has none registered.', $module->label),
                ['module' => $module->key]
            )->withNote($module->whyNoDepth());
        }

        if ($plan === null || ! $plan->runsAgent()) {
            return StageOutcome::skipped(
                'No agent run was needed — this route reads what the agent already produced.',
                ['route' => $plan?->route]
            )->withNote(
                'Re-running would re-analyse rather than explain, and would change the numbers a '
                . 'decision was already made on.'
            );
        }

        $run = $this->agents->run(
            $module->agentKey,
            $context->scope,
            $this->inputFor($context),
            'conversation',
            'ask:' . ($plan->intentKey ?? 'planned')
        );

        $context->agentRun = $run;

        return match ($run['status'] ?? 'failed') {
            'rejected' => $this->rejected($run, $module->agentKey),
            'failed' => $this->failed($run, $module->agentKey),
            default => $this->completed($context, $run, $module->agentKey),
        };
    }

    // ---------------------------------------------------------------- outcomes

    private function rejected(array $run, string $agentKey): StageOutcome
    {
        return StageOutcome::blocked(
            (string) ($run['summary'] ?? 'The agent was not permitted to run.'),
            ['agent_key' => $agentKey, 'status' => 'rejected']
        )->halting('The agent was not permitted to run, so nothing downstream happened.');
    }

    private function failed(array $run, string $agentKey): StageOutcome
    {
        return StageOutcome::blocked(
            'The agent run failed part-way through.',
            [
                'agent_key' => $agentKey,
                'status' => 'failed',
                'error' => $run['error'] ?? null,
                'run_reference' => $run['run_id'] ?? null,
                'counters_before_failure' => $run['counters'] ?? [],
                'what_this_is_not' => 'This is not a finding that no student is at risk. The analysis '
                    . 'did not complete, so nothing at all is known about the students it had not reached.',
            ]
        )->halting('The agent run failed part-way, so nothing downstream can be trusted to have run.');
    }

    private function completed(StageContext $context, array $run, string $agentKey): StageOutcome
    {
        $counters = $run['counters'] ?? [];
        $result = $run['result'] ?? [];

        $context->cases = is_array($result['cases'] ?? null) ? $result['cases'] : [];
        $context->focusCase = $context->cases[0] ?? null;
        $context->set('cohort', is_array($result['cohort'] ?? null) ? $result['cohort'] : []);
        $context->set(
            'detector_coverage',
            is_array($result['detector_coverage'] ?? null) ? $result['detector_coverage'] : []
        );

        return StageOutcome::ran(
            sprintf(
                'Agent ran (%s) — %d signal%s, %d case%s, %d recommendation%s.',
                $run['status'] ?? 'completed',
                $counters['signals_detected'] ?? 0,
                ($counters['signals_detected'] ?? 0) === 1 ? '' : 's',
                $counters['cases_opened'] ?? 0,
                ($counters['cases_opened'] ?? 0) === 1 ? '' : 's',
                $counters['recommendations_drafted'] ?? 0,
                ($counters['recommendations_drafted'] ?? 0) === 1 ? '' : 's'
            ),
            [
                'agent_key' => $agentKey,
                'why_this_agent' => sprintf(
                    'It is the agent bound to the %s module, and its manifest licenses the signals '
                    . 'this question needs.',
                    $context->module->label
                ),
                'run_reference' => $run['run_id'] ?? null,
                'status' => $run['status'] ?? null,
                'counters' => $counters,
                'verb_ceiling' => 'recommend — the agent may draft an action but cannot perform one',
                'summary' => $run['summary'] ?? null,
            ],
            ['table' => 'ai_agent_runs', 'ids' => array_filter([$run['run_id'] ?? null])],
            [
                'api' => 'GET ' . $this->prefix() . '/agent-runs?agent_key=' . $agentKey,
                'sql' => 'select * from ai_agent_runs where id = ' . ($run['run_id'] ?? 0),
            ]
        )->withComponent(sprintf('App\\Domain\\AI\\Agents\\AgentRunner -> %s', $agentKey));
    }

    /**
     * What to run the agent over.
     *
     * The MCP-resolved student wins over the intent's own slot, and that ordering is
     * the reason tool selection and the transport run before this stage: a question
     * naming a person carries a *name*, and only the lookup turns it into an id. Reading
     * the slot alone would leave `subject_id` null for "why is Ravi at risk?" and sweep
     * the whole cohort to answer a question about one child.
     *
     * @return array<string, mixed>
     */
    private function inputFor(StageContext $context): array
    {
        $resolved = $context->get('resolved_student');

        $subjectId = is_array($resolved) && isset($resolved['student_id'])
            ? (int) $resolved['student_id']
            : $context->intent?->slot('student_id');

        return array_filter([
            'subject_entity_key' => $context->module->entityKey ?? 'student',
            'subject_id' => $subjectId,
            'limit' => $context->options['limit'] ?? 50,
        ], static fn ($value) => $value !== null);
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
