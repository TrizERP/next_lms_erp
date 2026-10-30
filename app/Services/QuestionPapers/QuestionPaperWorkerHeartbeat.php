<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\Cache;

/**
 * Whether the QUESTION PAPERS queue worker is alive, so a paper nobody has picked up can say why.
 *
 * The worker beats on every poll of its queue and around every job (listeners in AppServiceProvider). Only the
 * `question_papers` connection beats: a worker for `default` (another feature's) must never make question papers look
 * healthy, which is why this is not the Course Presentation heartbeat.
 *
 * The answer is only ever as good as the cache the web app and the worker share, so it can be "unknown" (null) and the
 * screen then says nothing rather than something false. Nothing here starts or stops a worker.
 */
class QuestionPaperWorkerHeartbeat
{
    /** A polling worker (--sleep=3) beats every few seconds; this long without one means nobody is looking at the queue. */
    public const IDLE_FRESH_SECONDS = 60;

    /** Inside a job a worker beats only when it starts; the job timeout is 900 s. */
    public const BUSY_FRESH_SECONDS = 960;

    public static function beat(bool $busy = false): void
    {
        try {
            Cache::put(self::key(), ['at' => time(), 'busy' => $busy], self::BUSY_FRESH_SECONDS + 60);
        } catch (\Throwable) {
            // A cache that cannot be written only makes the worker "not detected"; it never stops a job.
        }
    }

    /**
     * @return 'active'|'busy'|'not_detected'|'inline'|null null = cannot tell (shared cache not guaranteed)
     */
    public static function state(?int $now = null): ?string
    {
        if (config('queue.default') === 'sync') {
            return 'inline';
        }
        if (!self::trusted()) {
            return null;
        }
        try {
            $beat = Cache::get(self::key());
        } catch (\Throwable) {
            return null; // an unreadable cache is "unknown", never "the worker is down"
        }
        if (!is_array($beat) || !isset($beat['at'])) {
            return 'not_detected';
        }
        $age = ($now ?? time()) - (int) $beat['at'];

        return !empty($beat['busy'])
            ? ($age <= self::BUSY_FRESH_SECONDS ? 'busy' : 'not_detected')
            : ($age <= self::IDLE_FRESH_SECONDS ? 'active' : 'not_detected');
    }

    /** When the worker last reported in (unix time), or null if it never has / the cache cannot be read. */
    public static function lastBeatAt(): ?int
    {
        try {
            $beat = Cache::get(self::key());
        } catch (\Throwable) {
            return null;
        }

        return is_array($beat) && isset($beat['at']) ? (int) $beat['at'] : null;
    }

    public static function key(): string
    {
        return (string) config('idms.question_paper.heartbeat_key', 'question-papers:queue-worker');
    }

    private static function trusted(): bool
    {
        $configured = config('idms.question_paper.heartbeat_trusted');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        // An in-process cache cannot be shared with another process, so its silence proves nothing.
        return !in_array(config('cache.default'), ['array', 'null'], true);
    }
}
