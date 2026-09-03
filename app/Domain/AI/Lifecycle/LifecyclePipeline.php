<?php

namespace App\Domain\AI\Lifecycle;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the twelve stages, in order, and records what each one said.
 *
 * This class is the whole reason the lifecycle is standardised. It owns the four things
 * every stage used to do for itself and get subtly wrong:
 *
 *   1. **Order.** Stages run in execution order and render in display order. Neither is
 *      a stage's business.
 *   2. **Timing.** Measured here, so no stage can forget it or report a number that
 *      excludes its own slowest call.
 *   3. **Halting.** One stage says "stop, and here is why"; every downstream stage is
 *      marked not-reached with that reason. The old service hand-wrote this list in five
 *      places and they had already drifted apart.
 *   4. **Failure.** A stage that throws becomes a `blocked` stage carrying the error and
 *      halts the turn. A crash is reported as a crash — never as an empty result, which
 *      is the failure mode that lets "the analysis died" render as "nobody is at risk".
 *
 * A stage is constructed by the container and knows nothing about its neighbours.
 */
final class LifecyclePipeline
{
    /** @var array<int, LifecycleStage> */
    private readonly array $stages;

    /**
     * @param  iterable<LifecycleStage>  $stages
     */
    public function __construct(iterable $stages)
    {
        $ordered = $stages instanceof \Traversable ? iterator_to_array($stages) : $stages;

        usort(
            $ordered,
            static fn (LifecycleStage $a, LifecycleStage $b) => $a->key()->executionOrder() <=> $b->key()->executionOrder()
        );

        $this->stages = array_values($ordered);
    }

    public function run(StageContext $context): LifecycleTrace
    {
        $trace = new LifecycleTrace();
        $halted = null;

        foreach ($this->stages as $stage) {
            $key = $stage->key();

            if ($halted !== null) {
                $trace->markNotReached($key, $halted);

                continue;
            }

            $startedAt = microtime(true);

            try {
                $outcome = $stage->run($context);
            } catch (Throwable $exception) {
                // Reporting must never become the second failure. `report()` and the
                // log both resolve out of the container, and a stage can fail in a
                // context where it is not fully booted — a console command, a queue
                // worker, a test. Losing the log entry is survivable; losing the
                // honest `blocked` outcome underneath it is not.
                try {
                    report($exception);

                    Log::warning('AI lifecycle stage failed.', [
                        'stage' => $key->value,
                        'question' => $context->question,
                        'module' => $context->module->key,
                        'exception' => $exception->getMessage(),
                    ]);
                } catch (Throwable) {
                    // Deliberately swallowed — see above.
                }

                $outcome = StageOutcome::blocked(
                    sprintf('%s failed part-way through.', $key->layer()),
                    [
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]
                )->halting(sprintf(
                    'The %s stage failed, so nothing downstream can be trusted to have run.',
                    $key->layer()
                ));
            }

            $trace->record(
                $key,
                $outcome->withDuration((int) round((microtime(true) - $startedAt) * 1000))
            );

            if ($outcome->halts()) {
                $halted = $outcome->halt;
            }
        }

        return $trace;
    }
}
