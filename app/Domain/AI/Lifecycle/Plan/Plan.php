<?php

namespace App\Domain\AI\Lifecycle\Plan;

/**
 * How a turn intends to answer one question.
 *
 * The distinction this class exists to protect is between what a plan *proposes* and
 * what a turn *does*. `candidateTools` is the former. It is not a record of tool use,
 * and the trace must never report it as one: a plan can name a lookup tool and then take
 * a route that never needs it, and a stage claiming "1 tool selected" while the next
 * stage reports no calls is a contradiction a reader cannot resolve.
 *
 * `refusals` is the other half of honest planning. Some questions cannot be answered
 * because the estate does not record the thing being asked about. Deciding that at plan
 * time — before any tool runs — is what produces "the school holds no competency
 * records" instead of a headcount presented as a capability judgement.
 */
final class Plan
{
    public const SOURCE_DETERMINISTIC = 'deterministic';
    public const SOURCE_LLM = 'llm';

    /**
     * @param  array<int, PlanStep>  $steps
     * @param  array<int, string>  $candidateTools  Proposed, not selected.
     * @param  array<int, array{when_unavailable:string, reason:string}>  $refusals
     * @param  array<string, mixed>  $context  Payload and inherited referents, for the trace.
     */
    public function __construct(
        public readonly string $goal,
        public readonly array $steps,
        public readonly string $source,
        public readonly string $route = 'conversation',
        public readonly ?string $intentKey = null,
        public readonly array $candidateTools = [],
        public readonly string $toolSelectionStrategy = 'domain_services_only',
        public readonly array $refusals = [],
        public readonly array $context = [],
    ) {
    }

    /** True when the plan intends to run the module's agent. */
    public function runsAgent(): bool
    {
        return $this->route === 'agent_runner';
    }

    /**
     * The refusal that applies, if the named data really is unavailable.
     *
     * @param  array<int, string>  $unavailable
     * @return array{when_unavailable:string, reason:string}|null
     */
    public function refusalFor(array $unavailable): ?array
    {
        foreach ($this->refusals as $refusal) {
            foreach ($unavailable as $missing) {
                if (stripos($refusal['when_unavailable'], $missing) !== false) {
                    return $refusal;
                }
            }
        }

        return null;
    }

    public function stepCount(): int
    {
        return count($this->steps);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'goal' => $this->goal,
            'source' => $this->source,
            'route' => $this->route,
            'intent' => $this->intentKey,
            'steps' => array_map(static fn (PlanStep $step) => $step->toArray(), $this->steps),
            'candidate_tools' => $this->candidateTools,
            'tool_selection_strategy' => $this->toolSelectionStrategy,
            'refusals' => $this->refusals,
        ] + $this->context;
    }
}
