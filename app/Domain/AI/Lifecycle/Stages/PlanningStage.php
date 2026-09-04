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
        )->withComponent($plan->source === Plan::SOURCE_LLM
            ? 'App\\Domain\\AI\\Lifecycle\\Plan\\LlmPlanner'
            : 'App\\Domain\\AI\\Lifecycle\\Plan\\DeterministicPlanner');
    }

    private function summarise(Plan $plan): string
    {
        $count = $plan->stepCount();

        $how = $plan->source === Plan::SOURCE_DETERMINISTIC
            ? sprintf('matched the "%s" intent in the registry', $plan->intentKey)
            : 'planned by the model and validated against this module\'s tool bindings';

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

        return StageOutcome::blocked(
            sprintf(
                'No registered intent matched, and the %s module has no tools bound that could answer it another way.',
                $module->label
            ),
            ['module' => $module->key, 'bound_tools' => $module->mcpTools]
        )->halting('Nothing ran: the question was not understood, and guessing would be worse.');
    }
}
