<?php

namespace App\Domain\AI\Lifecycle;

/**
 * What a stage did, in the only five words the lifecycle recognises.
 *
 * The string values match the legacy `TraceStage` constants exactly, so a stored turn
 * from the old trace and a turn from this pipeline render through the same frontend
 * without a translation layer.
 *
 * The distinction that matters most is `skipped` versus `not_reached`. A skipped stage
 * was offered the turn and had nothing to do — a normal, healthy outcome. A not-reached
 * stage never got the chance, because something upstream stopped. Collapsing the two is
 * what makes a governed pipeline look like a broken one.
 */
enum StageStatus: string
{
    /** The stage did work and has something to report. */
    case Ran = 'ran';

    /** The stage was reached, had nothing to do, and that is fine. */
    case Skipped = 'skipped';

    /** The stage refused: governance, role, or a missing decision. */
    case Blocked = 'blocked';

    /** The stage is legitimately waiting on something — nearly always a person. */
    case Pending = 'pending';

    /** The turn never got this far. */
    case NotReached = 'not_reached';

    /**
     * Which status wins when several backend stages fold into one lifecycle stage.
     *
     * Ordered by how much a reader needs to know about it: a refusal anywhere in a
     * folded group is the headline, and an all-skipped group is the quietest outcome
     * that still counts as having been reached.
     *
     * @param  array<int, StageStatus>  $statuses
     */
    public static function combine(array $statuses): self
    {
        foreach ([self::Blocked, self::Pending, self::Ran, self::Skipped] as $candidate) {
            if (in_array($candidate, $statuses, true)) {
                return $candidate;
            }
        }

        return self::NotReached;
    }

    /** True when the stage genuinely executed, whatever the result. */
    public function wasReached(): bool
    {
        return $this !== self::NotReached;
    }

    /** The short marker used by the CLI ladder. */
    public function marker(): string
    {
        return match ($this) {
            self::Ran => 'OK',
            self::Skipped => '--',
            self::Blocked => 'XX',
            self::Pending => '..',
            self::NotReached => '  ',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ran => 'Ran',
            self::Skipped => 'Skipped',
            self::Blocked => 'Refused',
            self::Pending => 'Waiting',
            self::NotReached => 'Not reached',
        };
    }
}
