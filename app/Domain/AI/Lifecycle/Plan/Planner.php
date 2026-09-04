<?php

namespace App\Domain\AI\Lifecycle\Plan;

use App\Domain\AI\Lifecycle\StageContext;

/**
 * Turns an understood question into a route.
 *
 * Returning null is a first-class answer: it means "I cannot plan this", which is what
 * lets the hybrid planner try the next strategy instead of forcing every planner to
 * invent something. A planner that guesses is worse than one that declines.
 */
interface Planner
{
    public function plan(StageContext $context): ?Plan;
}
