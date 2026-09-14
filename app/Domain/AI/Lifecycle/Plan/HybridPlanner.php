<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\StageContext;

/**
 * Deterministic first, model second.
 *
 * The ordering is the whole design. Every question the platform has been taught to
 * answer routes through code somebody can read, costs no tokens, and produces the same
 * route every time — which is what makes an approval replayable and an audit meaningful.
 * Only a question outside that set reaches the model, and when it does, its plan is
 * validated against the same tool bindings the deterministic path respects.
 *
 * The trace always says which planner produced the route, so determinism is a fact a
 * reader can check per turn rather than a property they have to take on trust. That
 * matters more than it sounds: "this system is deterministic" is unfalsifiable, while
 * "this turn was planned deterministically, and here is the intent that matched" is not.
 */
class HybridPlanner implements Planner
{
    public function __construct(
        private readonly DeterministicPlanner $deterministic,
        private readonly LlmPlanner $llm,
        private readonly ModuleReadPlanner $moduleRead,
    ) {
    }

    public function plan(StageContext $context): ?Plan
    {
        $plan = $this->deterministic->plan($context);

        if ($plan !== null) {
            return $plan;
        }

        // Consequential wording that failed to match an intent must not be handed to a
        // model to reinterpret. If "approve the thing" did not classify, the honest
        // outcome is that nothing was understood — not a model's best guess at which
        // record the user meant to change.
        //
        // The read fallback below is not reached either, and must not be: "approve the
        // admission" is not a request to list admissions.
        if ($this->soundsConsequential($context->question)) {
            return null;
        }

        if (! $context->module->supports('conversational')) {
            return null;
        }

        $plan = $this->llm->plan($context);

        if ($plan !== null) {
            return $plan;
        }

        // Third and last: read the module the question was asked on.
        //
        // Added because the two planners above share a failure mode that looks like a
        // broken product. The deterministic one routes only what the intent registry
        // knows; the model one needs a provider credential bound to `agent_reasoning`,
        // and on an estate where none is configured it returns null for everything. The
        // two together therefore refused every unmapped question, and stage 4 halted the
        // turn with stages 5-12 marked not-reached — for questions whose answer was one
        // bound read tool away.
        //
        // This runs last on purpose. It changes nothing about a question either planner
        // can already route: both are tried first and their plans returned unchanged, so
        // every intent that worked before still takes exactly the same path. It only
        // occupies the gap where the alternative was no answer at all.
        return $this->moduleRead->plan($context);
    }

    /**
     * Wording that would change a record if acted on.
     *
     * Deliberately broad. A false positive here costs a fallback to "I did not
     * understand that"; a false negative routes a half-understood instruction to a
     * planner that has no idea which row it refers to.
     */
    private function soundsConsequential(string $question): bool
    {
        return (bool) preg_match(
            '/\b(approve|approved|reject|decline|dismiss|confirm|authorise|authorize|sign[\s-]?off|'
            . 'delete|remove|cancel|create|assign|enrol|enroll|admit|proceed|go ahead)\b/i',
            $question
        );
    }
}
