<?php

namespace App\Domain\Workflow;

/**
 * What a step handler returns.
 *
 * `pause()` is the one that matters architecturally: a step that needs a human does
 * not block a thread or poll — it returns a paused result, the engine parks the run,
 * and the run resumes when an approval row is written. That is what makes the
 * approval gate a first-class state rather than a sleep.
 */
final class StepResult
{
    private function __construct(
        public readonly string $status,      // completed | paused | failed | skipped
        public readonly array $output = [],
        public readonly ?string $message = null,
        public readonly ?string $nextStepKey = null,
        public readonly ?string $pauseReason = null,
    ) {
    }

    public static function completed(array $output = [], ?string $message = null, ?string $nextStepKey = null): self
    {
        return new self('completed', $output, $message, $nextStepKey);
    }

    /** The step needs something outside the engine — usually a person. */
    public static function paused(string $reason, array $output = []): self
    {
        return new self('paused', $output, $reason, null, $reason);
    }

    public static function failed(string $message, array $output = []): self
    {
        return new self('failed', $output, $message);
    }

    /** Conditions were not met; carry on without running this step. */
    public static function skipped(?string $message = null, ?string $nextStepKey = null): self
    {
        return new self('skipped', [], $message, $nextStepKey);
    }

    public function isTerminal(): bool
    {
        return $this->status === 'failed';
    }

    public function shouldContinue(): bool
    {
        return in_array($this->status, ['completed', 'skipped'], true);
    }
}
