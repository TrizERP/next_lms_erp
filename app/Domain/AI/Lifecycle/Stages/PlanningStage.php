<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\Plan\HybridPlanner;
use App\Domain\AI\Lifecycle\Plan\Plan;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * Stage 4 — deciding how to answer, before spending anything on answering.
 *
 * This is the stage that stops the turn when there is nothing sensible to do. Nowhere
 * else can: stage 2 only reports what a sentence meant, and every stage after this one
 * assumes a route exists. So an unplannable question halts here, and every stage below
 * is marked not-reached carrying the reason — which is the correct outcome and needs to
 * read as a deliberate stop rather than a dead pipeline.
 *
 * Routing a half-understood question is strictly worse than refusing it. It would mean
 * running a cohort analysis, or recording a decision, against a record nobody chose.
 */
class PlanningStage implements LifecycleStage
{
    public function __construct(private readonly HybridPlanner $planner)
    {
    }

    public function key(): StageKey
    {
        return StageKey::Planning;
    }

    public function run(StageContext $context): StageOutcome
    {
        $plan = $this->planner->plan($context);

        if ($plan === null) {
            return $this->cannotPlan($context);
        }

        $context->plan = $plan;

        return StageOutcome::ran(
            $this->summarise($plan),
            $plan->toArray(),
        )->withComponent(match ($plan->source) {
            Plan::SOURCE_LLM => 'App\\Domain\\AI\\Lifecycle\\Plan\\LlmPlanner',
            Plan::SOURCE_MODULE_READ => 'App\\Domain\\AI\\Lifecycle\\Plan\\ModuleReadPlanner',
            default => 'App\\Domain\\AI\\Lifecycle\\Plan\\DeterministicPlanner',
        });
    }

    private function summarise(Plan $plan): string
    {
        $count = $plan->stepCount();

        $how = match ($plan->source) {
            Plan::SOURCE_DETERMINISTIC => sprintf('matched the "%s" intent in the registry', $plan->intentKey),
            // Says what actually happened. Claiming a registry match here would be the
            // one thing this trace must never do: describe a route as more certain than
            // it was.
            Plan::SOURCE_MODULE_READ => 'no intent route and no model plan, so the module\'s own '
                . 'read tools were used',
            default => 'planned by the model and validated against this module\'s tool bindings',
        };

        return sprintf(
            'A %d-step plan was prepared — %s.',
            $count,
            $how
        );
    }

    /**
     * Nothing to run, and the reason depends on why — which the user needs, because the
     * three causes have three different fixes.
     */
    private function cannotPlan(StageContext $context): StageOutcome
    {
        $module = $context->module;
        $intentUnknown = $context->intent === null || $context->intent->isUnknown();

        if (! $intentUnknown) {
            // The intent classified but no route exists for it in this module. That is a
            // configuration gap, not a comprehension failure, and saying so points at
            // the right file.
            return StageOutcome::blocked(
                sprintf(
                    'The question was understood as "%s", but the %s module has no route for that intent.',
                    $context->intent->label,
                    $module->label
                ),
                ['intent' => $context->intent->key, 'module' => $module->key]
            )->halting('No route was planned, so no stage below could run.');
        }

        if ($module->key === 'general') {
            return StageOutcome::blocked(
                'The question was not scoped to a module and matched no registered intent, so there '
                . 'was no route to plan.',
                ['module' => 'general', 'considered' => $context->get('modules_considered', [])]
            )->halting(
                'Nothing ran: routing a half-understood question would mean acting on the wrong record.'
            );
        }

        // The module is bound to tools, but nothing here is what stopped the turn — the
        // question named nothing any module's vocabulary recognises, this module's own
        // data included. "What can I do in this system?" asked on the Fees screen used to
        // read this as a fees question because Fees binds tools, blindly read the fee
        // ledger for it, and reported that no student or case could be found — a decoy
        // for a question that was never about a case. The fix is to say what actually
        // happened: the screen's data was not what was asked about.
        if ($context->get('modules_considered', []) === [] && $module->mcpTools !== []) {
            return StageOutcome::blocked(
                sprintf(
                    'That is not a question about %s\'s own data, so there was nothing here to look up.',
                    $module->label
                ),
                ['module' => $module->key, 'bound_tools' => $module->mcpTools]
            )->halting(
                'Nothing ran: ask about a student, a fee, attendance, admissions or another module by '
                . 'name, or ask what this screen can do.'
            );
        }

        return StageOutcome::blocked(
            sprintf(
                'No registered intent matched, and the %s module has no tools bound that could answer it another way.',
                $module->label
            ),
            ['module' => $module->key, 'bound_tools' => $module->mcpTools]
        )->halting('Nothing ran: the question was not understood, and guessing would be worse.');
    }
}
