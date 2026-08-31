<?php

namespace App\Domain\AI\Lifecycle;

/**
 * The twelve stages of one turn, as they actually executed.
 *
 * Every stage exists on the trace from the moment it is constructed. A stage that never
 * ran stays in the trace as `not_reached` with a reason, because "Action did not run,
 * because nothing has been approved yet" is exactly the fact a reader needs. Silence
 * would leave them unable to tell an idle stage from a missing one.
 *
 * The recorder decides nothing. The pipeline runs the stages; this holds what they said.
 */
final class LifecycleTrace implements RecordableTrace
{
    /** @var array<string, StageOutcome> */
    private array $outcomes = [];

    private readonly float $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);

        foreach (StageKey::cases() as $key) {
            $this->outcomes[$key->value] = StageOutcome::notReached(
                'This stage was not reached on this turn.'
            );
        }
    }

    public function record(StageKey $key, StageOutcome $outcome): void
    {
        $this->outcomes[$key->value] = $outcome;
    }

    /**
     * Mark a stage not reached, giving the reason the halting stage supplied.
     *
     * Only overwrites a stage that has not reported. A stage that genuinely ran before
     * something later halted keeps its own report — the halt stops what follows, it does
     * not rewrite history.
     */
    public function markNotReached(StageKey $key, string $why): void
    {
        if ($this->statusOf($key) === StageStatus::NotReached) {
            $this->outcomes[$key->value] = StageOutcome::notReached($why);
        }
    }

    public function outcomeOf(StageKey $key): StageOutcome
    {
        return $this->outcomes[$key->value];
    }

    public function statusOf(StageKey $key): StageStatus
    {
        return $this->outcomes[$key->value]->status;
    }

    /**
     * The stages, in the order the console renders them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (StageKey $key) => $this->outcomes[$key->value]->toArray($key),
            StageKey::inDisplayOrder()
        );
    }

    /**
     * One line per stage, for the CLI and for anyone reading the JSON without a UI.
     *
     * @return array<int, string>
     */
    public function toLadder(): array
    {
        return array_map(function (StageKey $key) {
            $outcome = $this->outcomes[$key->value];

            return sprintf(
                '[%s] %-22s %s',
                $outcome->status->marker(),
                $key->layer(),
                $outcome->summary !== ''
                    ? $outcome->summary
                    : ($outcome->note ?? 'not reached in this turn')
            );
        }, StageKey::inDisplayOrder());
    }

    /** @return array<string, int> */
    public function summaryCounts(): array
    {
        $counts = [];

        foreach ($this->outcomes as $outcome) {
            $counts[$outcome->status->value] = ($counts[$outcome->status->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * How far down the ladder the turn got before it stopped having anything to say.
     *
     * Counts stages that were *reached*, not stages that ran — a turn that legitimately
     * skips six stages and waits at a human gate has covered the whole lifecycle, and
     * scoring it six-out-of-twelve would misread a governed stop as a failure.
     */
    public function depthReached(): int
    {
        $depth = 0;

        foreach (StageKey::inDisplayOrder() as $key) {
            if ($this->statusOf($key)->wasReached()) {
                $depth = $key->displayOrder();
            }
        }

        return $depth;
    }

    public function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }
}
