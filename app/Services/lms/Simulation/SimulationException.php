<?php

namespace App\Services\lms\Simulation;

use RuntimeException;

/**
 * A simulation, or a request about one, that cannot be accepted. `$problems` are plain sentences for the person who sent it, one per
 * thing that is wrong, so the screen can list them; `getCode()` is the HTTP status to answer with (422 unless it says otherwise).
 */
class SimulationException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems, int $status = 422)
    {
        parent::__construct(implode(' ', $problems), $status);
    }
}
