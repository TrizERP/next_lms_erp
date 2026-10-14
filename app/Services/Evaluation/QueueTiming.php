<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Facades\Log;

/**
 * Notices when a queue is set up so that a job can be delivered twice.
 *
 * A queued job is leased to one worker for `retry_after` seconds; if it has not finished
 * by then the queue assumes the worker died and gives the job to another. A job whose own
 * `$timeout` is longer than that lease therefore runs twice whenever it runs long -- and a
 * sheet being read by two workers at once is exactly what its overlap lock and status
 * checks are there to survive, not to rely on.
 *
 * Nothing here changes behaviour. It looks at the connection the job is actually running
 * on and says so, once per process, in the log. Nothing in the message identifies a
 * student, a school or a sheet.
 */
final class QueueTiming
{
    /** @var array<string,true> */
    private static array $warned = [];

    /** The lease on the named connection (default connection when null); null for drivers without one (sync, SQS). */
    public static function retryAfter(?string $connection): ?int
    {
        $connection ??= (string) config('queue.default');
        $value = config("queue.connections.{$connection}.retry_after");

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Logs a warning (once per process, per job class) when the lease is not longer than the
     * job's timeout. Returns whether it did, for the tests.
     */
    public static function warnIfLeaseIsShorterThanTimeout(string $job, int $timeout, ?string $connection): bool
    {
        $lease = self::retryAfter($connection);

        if ($lease === null || $lease > $timeout || isset(self::$warned[$job])) {
            return false;
        }

        self::$warned[$job] = true;

        Log::warning('Queue retry_after is not longer than this job\'s timeout, so a job that runs long can be delivered to a second worker', [
            'job' => $job,
            'job_timeout_seconds' => $timeout,
            'retry_after_seconds' => $lease,
            'connection' => $connection ?? (string) config('queue.default'),
            'set' => 'QUEUE_RETRY_AFTER >= ' . ($timeout + 60),
        ]);

        return true;
    }

    /** For tests: a fresh process has not warned yet. */
    public static function forgetWarnings(): void
    {
        self::$warned = [];
    }
}
