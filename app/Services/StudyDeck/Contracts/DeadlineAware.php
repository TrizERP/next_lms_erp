<?php

namespace App\Services\StudyDeck\Contracts;

/**
 * A completer that can be told when to stop: no call starts, waits or retries past the time. Planners read it to stop repair
 * rounds that could not finish. With no deadline set nothing changes.
 */
interface DeadlineAware
{
    /** @param float|null $unixTime microtime(true) after which no model call is started or waited for */
    public function setDeadline(?float $unixTime): void;

    public function deadline(): ?float;
}
