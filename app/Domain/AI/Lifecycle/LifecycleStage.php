<?php

namespace App\Domain\AI\Lifecycle;

/**
 * One lifecycle stage. All twelve implement exactly this and nothing else.
 *
 * The interface is deliberately two methods wide. Everything a stage needs arrives on
 * the context; everything it has to say leaves as a StageOutcome. A stage cannot write
 * to the trace, cannot time itself, cannot decide whether a later stage runs, and cannot
 * reach into another stage's payload — those are the runner's jobs, and keeping them
 * there is what makes the twelve interchangeable rather than twelve special cases.
 *
 * Stages fall into two honest roles, and both use this same contract:
 *
 *   - **Producers** (1-6) do the work and leave artifacts on the context: the thread,
 *     the intent, the plan, the selected tools, the MCP results, the agent run.
 *   - **Reporters** (7-12) read those artifacts, plus stored state, and report what the
 *     turn achieved. They are not decorative — a reporter is what turns "the agent ran"
 *     into "eleven evidence rows, of which nine are verified, cited by case #42".
 *
 * A stage that has nothing to do must return `skipped` with a reason. Returning `ran`
 * with an empty summary is the one thing the frontend renders as a defect.
 */
interface LifecycleStage
{
    /** Which of the twelve this is. One stage class per key, no exceptions. */
    public function key(): StageKey;

    /**
     * Do the work, or explain why there was none.
     *
     * Must not throw for ordinary failure — a refusal is `blocked`, an outage is
     * `blocked` with the error in `data`. The runner catches anything that escapes and
     * converts it, but a stage that reports its own failure can say something useful
     * about it, and a stage that throws cannot.
     */
    public function run(StageContext $context): StageOutcome;
}
