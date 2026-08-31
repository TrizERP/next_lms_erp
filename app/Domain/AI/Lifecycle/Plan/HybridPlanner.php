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
        if ($this->soundsConsequential($context->question)) {
            return null;
        }

        if (! $context->module->supports('conversational')) {
            return null;
        }

        return $this->llm->plan($context);
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
