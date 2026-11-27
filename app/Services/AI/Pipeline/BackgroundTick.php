<?php

namespace App\Services\AI\Pipeline;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps one detached `php artisan ai:pipeline-worker` alive while any Users, Attendance or Fees
 * automation is active.
 *
 * Laravel's scheduler only runs when `schedule:run` / `schedule:work` is running, and a host with
 * neither would leave a "continuous" automation idle as soon as the browser tab that pressed Start
 * is closed or throttled. The worker is what makes Start a one-time action: it ticks every few
 * seconds, runs each due cycle, and retires on its own once nothing is active.
 *
 * Starting it is idempotent (a heartbeat in the cache says whether one is already alive), and every
 * runner takes the same cycle lease, so a second runner can only ever be harmless.
 */
class BackgroundTick
{
    /** A screen that is open gets this long to run a due cycle itself before the worker takes it. */
    public const GRACE_SECONDS = 20;

    public const HEARTBEAT_KEY = 'pipeline_worker:alive';

    public const HEARTBEAT_TTL = 300;

    /** Start the worker unless one is already alive. Returns true when one was started. */
    public static function ensureWorker(): bool
    {
        try {
            if (Cache::has(self::HEARTBEAT_KEY)) {
                return false;
            }
            // Two requests arriving together must not both launch one.
            if (! Cache::add('pipeline_worker:launch', 1, 20)) {
                return false;
            }

            $php = preg_match('/php(\.exe)?$/i', PHP_BINARY) ? PHP_BINARY : 'php';
            $artisan = base_path('artisan');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B "" "' . $php . '" "' . $artisan . '" ai:pipeline-worker > NUL 2>&1', 'r'));
            } else {
                exec('"' . $php . '" "' . $artisan . '" ai:pipeline-worker > /dev/null 2>&1 &');
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Could not start the automation worker: ' . $e->getMessage());

            return false;
        }
    }
}
