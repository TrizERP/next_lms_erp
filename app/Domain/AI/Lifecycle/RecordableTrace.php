<?php

namespace App\Domain\AI\Lifecycle;

/**
 * A trace that can be persisted against a conversation turn.
 *
 * Two traces satisfy this: the legacy fifteen-stage `FlowTrace` and the twelve-stage
 * `LifecycleTrace`. They report different ladders, and deliberately so — the backend
 * ladder is a diagnostic, the lifecycle is the product — but they store identically, so
 * a turn recorded before the migration and one recorded after are both readable by the
 * same console and the same replay.
 *
 * This interface exists so the store does not have to know which pipeline produced the
 * turn. That is what makes the cutover incremental rather than a flag day.
 */
interface RecordableTrace
{
    /**
     * The stages, in display order, ready to render.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array;

    /**
     * How many stages hold each status.
     *
     * @return array<string, int>
     */
    public function summaryCounts(): array;
}
