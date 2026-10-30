<?php

namespace App\Services\QuestionPapers\Worker;

use App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Status, start and stop of the Question Paper worker, for the control panel.
 *
 * Rules it keeps:
 *  - Only the allowlisted worker (WorkerSignature) is ever started, counted or stopped; the default queue's worker and
 *    every other process are invisible to it.
 *  - A state is reported only after it is VERIFIED against the real process (or service manager). "Running" needs a live
 *    worker process (and a fresh heartbeat when the heartbeat can be trusted); "stopped" needs none left.
 *  - A start while a worker exists is refused (no duplicate), and start/stop are serialised by an OS lock.
 *  - Stop is graceful: the worker finishes the job it is on. If that takes longer than the wait, the answer is "stopping",
 *    not "stopped".
 *  - What the operator asked for is remembered ("desired"). A manual stop therefore overrides automatic starting until Start
 *    is pressed again.
 */
class QuestionPaperWorkerManager
{
    /** After a start, how long a missing worker is still "starting" rather than a failure. */
    public const START_GRACE_SECONDS = 30;

    /** After a stop request, how long a still-running worker is "stopping": a job can run up to 900 s. */
    public const STOP_GRACE_SECONDS = 960;

    /** Automatic restarts of a worker that died unexpectedly (drivers whose host does not restart it): at most this many... */
    public const MAX_AUTO_RESTARTS = 3;

    /** ...within this many seconds. */
    public const AUTO_RESTART_WINDOW = 600;

    public function __construct(
        private WorkerDriver $driver,
        private WorkerStateStore $store,
        private WorkerLock $lock,
        private int $startWaitSeconds = 20,
        private int $stopWaitSeconds = 10,
    ) {
    }

    /** The manager for this server's configuration. */
    public static function fromConfig(): self
    {
        $cfg = (array) config('idms.question_paper.worker', []);
        $runner = new SymfonyCommandRunner();
        $driver = (string) ($cfg['driver'] ?? 'auto');
        if ($driver === 'auto') {
            // Local development controls the worker itself; anywhere else nothing is assumed.
            $driver = app()->environment('local') ? 'launcher' : 'none';
        }

        $store = new WorkerStateStore();
        $instance = match ($driver) {
            'launcher' => new LauncherDriver(new SystemProcessTable(), $store, $runner, (array) ($cfg['env'] ?? [])),
            'supervisor' => new SupervisorDriver($runner, (string) ($cfg['supervisor_program'] ?? 'question-papers-worker'), (bool) ($cfg['sudo'] ?? false)),
            'systemd' => new SystemdDriver($runner, (string) ($cfg['systemd_unit'] ?? 'question-papers-worker'), (bool) ($cfg['sudo'] ?? false)),
            default => new UnavailableDriver(),
        };

        return new self($instance, $store, new WorkerLock(), (int) ($cfg['start_wait'] ?? 20), (int) ($cfg['stop_wait'] ?? 10));
    }

    /* ---------- status ---------- */

    /**
     * @param  bool  $reconcile  let a driver without a host-level supervisor restart a worker that died unexpectedly
     * @return array<string, mixed>
     */
    public function status(bool $reconcile = false): array
    {
        $reason = WorkerSignature::configurationError() ?? $this->driver->unsupportedReason();
        if ($reason !== null) {
            return $this->payload('unsupported', $reason, 0, null, $this->readState(), null, false);
        }

        try {
            $info = $this->driver->inspect();
        } catch (Throwable $e) {
            return $this->payload('error', 'The worker\'s state could not be read: ' . $e->getMessage(), 0, null, $this->readState(), $e->getMessage(), false);
        }

        $state = $this->readState();
        $computed = $this->compute($info, $state);

        if ($reconcile && $computed['state'] === 'error' && $computed['unexpected'] && !$this->driver->managesRestarts()) {
            $restarted = $this->tryAutoRestart($state);
            if ($restarted !== null) {
                try {
                    $info = $this->driver->inspect();
                } catch (Throwable $e) {
                    return $this->payload('error', 'The worker\'s state could not be read: ' . $e->getMessage(), 0, null, $this->readState(), $e->getMessage(), false);
                }
                $state = $this->readState();
                $computed = $this->compute($info, $state);
                if ($restarted !== '') {
                    $computed['message'] = $restarted;
                }
            }
        }

        return $this->payload($computed['state'], $computed['message'], (int) $info['workers'], $info['detail'], $state, $computed['error'] ?? null);
    }

    /**
     * @param  array{workers: int, transitional: ?string, detail: ?string}  $info
     * @param  array<string, mixed>  $state
     * @return array{state: string, message: string, unexpected: bool, error: ?string}
     */
    private function compute(array $info, array $state): array
    {
        $workers = (int) $info['workers'];
        $last = $state['last_action'] ?? null;
        $age = $last ? time() - (int) $last['at'] : PHP_INT_MAX;
        $desired = $state['desired'] ?? null;

        if ($info['transitional'] === 'stopping' || ($workers > 0 && ($last['action'] ?? null) === 'stop' && $age <= self::STOP_GRACE_SECONDS && empty($state['last_error']))) {
            return $this->r('stopping', 'Stopping: the worker is finishing the job it is on and will then exit.');
        }
        if ($info['transitional'] === 'starting') {
            return $this->r('starting', 'Starting: the service manager is bringing the worker up.');
        }
        if ($workers > 0 && $desired === 'stopped' && ($last['action'] ?? null) === 'stop') {
            $why = $state['last_error'] ?? 'The worker did not stop within the graceful-stop window.';

            return ['state' => 'error', 'message' => 'The worker could not be verified as stopped. ' . $why, 'unexpected' => false, 'error' => (string) $why];
        }
        if ($workers > 0) {
            $freshStart = ($last['action'] ?? null) === 'start' && $age <= self::START_GRACE_SECONDS;
            if ($freshStart && !$this->heartbeatOk()) {
                return $this->r('starting', 'Starting: the worker process is up and has not reported in yet.');
            }

            return $this->r('running', $workers > 1
                ? "Running with {$workers} worker processes. Only one is expected: stop and start it again to clear the extra."
                : 'Running: the worker is processing the question-papers queue.');
        }

        // No worker process.
        if (($last['action'] ?? null) === 'start' && $age <= self::START_GRACE_SECONDS) {
            return $this->r('starting', 'Starting: waiting for the worker process to appear.');
        }
        if ($desired === 'running' && !$this->store->stopRequested()) {
            $why = $info['detail'] ?? ($state['last_error'] ?? 'The worker is not running although it was started.');

            return ['state' => 'error', 'message' => 'The worker stopped unexpectedly. ' . $why, 'unexpected' => true, 'error' => (string) $why];
        }

        return $this->r('stopped', $desired === 'stopped'
            ? 'Stopped by an operator. It stays stopped until Start worker is pressed.'
            : 'Stopped: no worker is running, so new question papers will wait in the queue.');
    }

    /** @return array{state: string, message: string, unexpected: bool, error: ?string} */
    private function r(string $state, string $message): array
    {
        return ['state' => $state, 'message' => $message, 'unexpected' => false, 'error' => null];
    }

    /* ---------- start / stop ---------- */

    /**
     * @return array{http: int, status: array<string, mixed>}
     *
     * @throws WorkerBusy
     */
    public function start(?string $by = null): array
    {
        return $this->lock->run(function () use ($by) {
            $status = $this->status();
            if ($status['state'] === 'unsupported') {
                return ['http' => 501, 'status' => $status];
            }
            if (!$status['process_state_readable']) {
                return ['http' => 503, 'status' => $status];
            }
            if ($status['state'] === 'starting') {
                $status['message'] = 'The worker is already starting. Nothing else was started.';

                return ['http' => 409, 'status' => $status];
            }
            if ($status['active_workers'] > 0) {
                // Never a second worker, not even while the first is still finishing a job after a stop.
                $status['message'] = $status['state'] === 'stopping'
                    ? 'The worker is still finishing its job. Wait until it has stopped, then start it again.'
                    : 'A worker is already running. Nothing was started.';

                return ['http' => 409, 'status' => $status];
            }

            $this->store->update(['desired' => 'running', 'last_action' => ['action' => 'start', 'at' => time(), 'by' => $by], 'last_error' => null]);
            try {
                $this->driver->start();
            } catch (Throwable $e) {
                $this->store->update(['last_error' => $e->getMessage()]);

                return ['http' => 500, 'status' => $this->status()];
            }

            try {
                $verified = $this->waitFor(fn () => $this->isVerifiedRunning(), $this->startWaitSeconds);
            } catch (Throwable $e) {
                $this->store->update(['last_error' => $e->getMessage()]);

                return ['http' => 503, 'status' => $this->status()];
            }
            $status = $this->status();
            if (!$verified && $status['state'] === 'starting') {
                return ['http' => 202, 'status' => $status];
            }

            return ['http' => $status['state'] === 'running' ? 200 : 500, 'status' => $status];
        });
    }

    /**
     * @return array{http: int, status: array<string, mixed>}
     *
     * @throws WorkerBusy
     */
    public function stop(?string $by = null): array
    {
        return $this->lock->run(function () use ($by) {
            $status = $this->status();
            if ($status['state'] === 'unsupported') {
                return ['http' => 501, 'status' => $status];
            }
            if (!$status['process_state_readable']) {
                return ['http' => 503, 'status' => $status];
            }

            $this->store->update(['desired' => 'stopped', 'last_action' => ['action' => 'stop', 'at' => time(), 'by' => $by], 'last_error' => null]);
            try {
                $this->driver->stop();
            } catch (Throwable $e) {
                $this->store->update(['last_error' => $e->getMessage()]);

                return ['http' => 500, 'status' => $this->status()];
            }

            try {
                $this->waitFor(fn () => (int) $this->driver->inspect()['workers'] === 0, $this->stopWaitSeconds);
            } catch (Throwable $e) {
                $this->store->update(['last_error' => $e->getMessage()]);

                return ['http' => 503, 'status' => $this->status()];
            }
            $status = $this->status();

            // A worker in the middle of a long job is still "stopping": not stopped, and not killed.
            $http = $status['state'] === 'stopped' ? 200 : ($status['state'] === 'stopping' ? 202 : 500);

            return ['http' => $http, 'status' => $status];
        });
    }

    /* ---------- internals ---------- */

    private function isVerifiedRunning(): bool
    {
        return (int) $this->driver->inspect()['workers'] > 0 && $this->heartbeatOk();
    }

    /** True when the worker reports in, or when the heartbeat cannot be trusted here (then the process alone is the evidence). */
    private function heartbeatOk(): bool
    {
        $state = QuestionPaperWorkerHeartbeat::state();

        return $state === null || in_array($state, ['active', 'busy'], true);
    }

    private function waitFor(callable $condition, int $seconds): bool
    {
        $deadline = microtime(true) + $seconds;
        do {
            if ($condition()) {
                return true;
            }
            usleep(500_000);
        } while (microtime(true) < $deadline);

        return (bool) $condition();
    }

    /**
     * Restart a worker that died unexpectedly, within a budget. Returns null when it did not try, '' when it restarted
     * silently, or a message to show.
     *
     * @param  array<string, mixed>  $state
     */
    private function tryAutoRestart(array $state): ?string
    {
        $recent = array_values(array_filter((array) ($state['restarts'] ?? []), fn ($t) => time() - (int) $t <= self::AUTO_RESTART_WINDOW));
        if (count($recent) >= self::MAX_AUTO_RESTARTS) {
            return null; // budget spent: stay in "error" and say so, rather than loop
        }

        try {
            return $this->lock->run(function () use ($recent) {
                if ((int) $this->driver->inspect()['workers'] > 0 || $this->store->stopRequested()) {
                    return null;
                }
                $recent[] = time();
                $this->store->update(['restarts' => $recent, 'last_action' => ['action' => 'start', 'at' => time(), 'by' => 'automatic restart'], 'last_error' => 'The worker exited unexpectedly and was restarted automatically (' . count($recent) . ' of ' . self::MAX_AUTO_RESTARTS . ' in 10 minutes).']);
                $this->driver->start();

                return '';
            });
        } catch (WorkerBusy) {
            return null;
        } catch (Throwable $e) {
            $this->store->update(['last_error' => 'Automatic restart failed: ' . $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        return $this->store->read();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function payload(string $state, string $message, int $workers, ?string $detail, array $state2, ?string $error = null, bool $processStateReadable = true): array
    {
        $beat = QuestionPaperWorkerHeartbeat::lastBeatAt();
        $pending = ['queue_messages' => null, 'open_jobs' => null];
        try {
            $pending['queue_messages'] = DB::table('jobs')->where('queue', WorkerSignature::queue())->whereNull('reserved_at')->count();
            $pending['open_jobs'] = DB::table('question_paper_jobs')->whereIn('status', ['queued', 'processing', 'retrying'])->count();
        } catch (Throwable) {
            // a missing table or an unreachable database leaves the count unknown (null), never a made-up zero
        }

        return [
            'state' => $state,
            'message' => $message,
            'driver' => $this->driver->name(),
            'supported' => $state !== 'unsupported' && $processStateReadable,
            'process_state_readable' => $processStateReadable,
            'unsupported_reason' => $state === 'unsupported' ? $message : null,
            'active_workers' => $processStateReadable ? $workers : null,
            'queue' => WorkerSignature::queue(),
            'pending' => $pending,
            'heartbeat' => [
                'state' => QuestionPaperWorkerHeartbeat::state(),
                'last_beat_at' => $beat ? date('c', $beat) : null,
                'age_seconds' => $beat ? max(0, time() - $beat) : null,
            ],
            'desired' => $state2['desired'] ?? null,
            'last_action' => $state2['last_action'] ?? null,
            'last_error' => $error ?? ($state2['last_error'] ?? null) ?: $detail,
            'restart_policy' => $this->driver->managesRestarts()
                ? 'The configured supervisor restarts the worker automatically if it crashes. Stopping it here keeps it stopped until it is started again.'
                : ($state === 'unsupported' ? null : 'If the worker exits unexpectedly it is restarted automatically (at most ' . self::MAX_AUTO_RESTARTS . ' times in 10 minutes) while this page is open. Stopping it here keeps it stopped until it is started again.'),
            'checked_at' => date('c'),
        ];
    }
}
