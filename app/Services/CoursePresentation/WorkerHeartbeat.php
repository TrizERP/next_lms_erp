<?php

namespace App\Services\CoursePresentation;

use Illuminate\Support\Facades\Cache;

/**
 * Whether a queue worker is alive, so a run that nobody has picked up can say so instead of waiting in silence. A worker
 * calls beat() on every poll of the queue and around every job (see AppServiceProvider); state() reads that back.
 * Nothing here starts or stops a worker.
 */
class WorkerHeartbeat
{
    private const KEY = 'course-presentation:queue-worker';
    /** A polling worker beats every few seconds; this long without one means no worker is looking at the queue. */
    private const IDLE_FRESH_SECONDS = 60;
    /** A worker inside a job beats only when the job starts; the job's own time limit bounds it. */
    private const BUSY_FRESH_SECONDS = 7500;

    public static function beat(bool $busy = false): void
    {
        try {
            Cache::put(self::KEY, ['at' => time(), 'busy' => $busy], self::BUSY_FRESH_SECONDS);
        } catch (\Throwable) {
            // A cache that cannot be written only makes the worker "not detected"; it never stops a job.
        }
    }

    /** @return 'inline'|'active'|'busy'|'not_detected' */
    public static function state(?int $now = null): string
    {
        if (config('queue.default') === 'sync') {
            return 'inline';
        }
        try {
            $beat = Cache::get(self::KEY);
        } catch (\Throwable) {
            return 'not_detected';
        }
        if (!is_array($beat) || !isset($beat['at'])) {
            return 'not_detected';
        }
        $age = ($now ?? time()) - (int) $beat['at'];

        return !empty($beat['busy'])
            ? ($age <= self::BUSY_FRESH_SECONDS ? 'busy' : 'not_detected')
            : ($age <= self::IDLE_FRESH_SECONDS ? 'active' : 'not_detected');
    }
}
