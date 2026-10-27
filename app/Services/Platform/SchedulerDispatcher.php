<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Runs the scheduled tasks a school has configured.
 *
 * `tick()` is called every minute by `platform:schedule-run`. It finds each
 * enabled task whose next cron time (computed from its last run, never stored)
 * has passed, takes a lock so two ticks cannot run it twice, runs its handler and
 * records last_run_at / last_run_status.
 *
 * HANDLERS. A task only runs if a handler is registered for its key below. A
 * configured task with no handler is NOT marked ok: it is reported back as
 * `no_handler` so the screen can say so. Add a handler here when a module's job
 * is ready to run; configuration for the task already exists in the registry.
 */
class SchedulerDispatcher
{
    /** @return array<string,callable(PlatformScheduledTask):(string|array)> task_key => handler returning a summary string, or {summary, findings} */
    private function handlers(): array
    {
        return array_merge(ScheduledJobs::registry(), [
            'reports.scheduled.dispatch' => function (): string {
                $n = app(ReportScheduler::class)->runDue();

                return "Ran {$n} scheduled reports.";
            },
            'communication.campaign.dispatch_queue' => function (): string {
                $n = app(NotificationSender::class)->retryDue();

                return "Retried {$n} queued or failed notifications.";
            },
        ]);
    }

    /** Whether a job is registered for this task key, so a screen can say so before anyone presses Run now. */
    public static function hasJob(string $taskKey): bool
    {
        return array_key_exists($taskKey, (new self())->handlers());
    }

    /** @return array{ran:int,failed:int,no_handler:int,escalated:int} */
    public function tick(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $summary = ['ran' => 0, 'failed' => 0, 'no_handler' => 0, 'escalated' => 0];

        // Engine housekeeping that belongs to no single school's task list.
        $summary['escalated'] = app(WorkflowEngine::class)->escalateOverdue();

        // Every school, every task that has a job. A school does NOT need a row in
        // platform_scheduled_tasks for its tasks to run: a row only exists once someone
        // saves a schedule, so requiring one meant most schools' tasks never ran.
        // Without a row the shipped default schedule applies; the row is created the
        // first time the task runs, so its last run shows on the screen.
        $catalogue = (array) config('platform_services.tasks', []);
        $keys = array_values(array_filter(array_keys($catalogue), fn ($key) => self::hasJob($key)));
        $tenants = DB::table('school_setup')->pluck('Id')->map(fn ($id) => (int) $id)->all();

        $rows = PlatformScheduledTask::query()->whereIn('task_key', $keys)->get()
            ->keyBy(fn ($row) => $row->sub_institute_id.'|'.$row->task_key);

        foreach ($tenants as $tenant) {
            foreach ($keys as $key) {
                $task = $rows[$tenant.'|'.$key] ?? null;

                if ($task) {
                    if ($task->disabled || ! $this->isDue($task, $now)) {
                        continue;
                    }
                } else {
                    if (! empty($catalogue[$key]['disabled_by_default']) || ! $this->defaultIsDue($catalogue[$key], $now)) {
                        continue;
                    }
                    $task = $this->createDefaultRow($tenant, $key, $catalogue[$key]);
                }

                $result = $this->run($task, $now);
                $summary[$result['status'] === 'ok' ? 'ran' : ($result['status'] === 'no_handler' ? 'no_handler' : 'failed')]++;
            }
        }

        return $summary;
    }

    /** True when the shipped schedule has a run time in the minute that just passed. */
    private function defaultIsDue(array $definition, CarbonImmutable $now): bool
    {
        try {
            $next = (new CronSchedule((array) ($definition['schedule'] ?? [])))->nextRunAt($now->subMinute());
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return $next !== null && $next->lessThanOrEqualTo($now);
    }

    private function createDefaultRow(int $tenant, string $key, array $definition): PlatformScheduledTask
    {
        $schedule = (array) ($definition['schedule'] ?? []);
        $parts = explode('.', $key);

        return PlatformScheduledTask::firstOrCreate(
            ['sub_institute_id' => $tenant, 'task_key' => $key],
            [
                'module' => $parts[0], 'component' => $parts[0].'.'.($parts[1] ?? ''),
                'minute' => (string) ($schedule['minute'] ?? '0'), 'hour' => (string) ($schedule['hour'] ?? '0'),
                'day' => (string) ($schedule['day'] ?? '*'), 'month' => (string) ($schedule['month'] ?? '*'),
                'day_of_week' => (string) ($schedule['day_of_week'] ?? '*'), 'disabled' => false, 'fail_delay' => 0,
                'updated_by' => 'scheduler',
            ]
        );
    }

    /** @return array{status:string,message:string} */
    public function run(PlatformScheduledTask $task, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $handler = $this->handlers()[$task->task_key] ?? null;
        if ($handler === null) {
            return ['status' => 'no_handler', 'message' => 'No job is registered for this task yet, so nothing was run.'];
        }

        $lock = Cache::lock('platform-task-'.$task->id, 300);
        if (! $lock->get()) {
            return ['status' => 'locked', 'message' => 'This task is already running.'];
        }

        try {
            ['summary' => $message, 'findings' => $findings] = self::normalise($handler($task));
            $task->forceFill(['last_run_at' => $now, 'last_run_status' => 'ok'])->save();
            AuditTrail::record((int) $task->sub_institute_id, (string) $task->module, (string) $task->component, 'task_run', [
                'entity_type' => 'scheduled_task', 'entity_id' => $task->task_key, 'after' => ['status' => 'ok', 'message' => $message],
            ]);

            // After the run is recorded, so a notification problem can never fail or block it. Covers run-now too,
            // because EngineController::runTaskNow goes through this method; the notifier's fingerprint check stops repeats.
            $this->notifyFindings($task, $findings);

            return ['status' => 'ok', 'message' => $message];
        } catch (Throwable $e) {
            $task->forceFill(['last_run_at' => $now, 'last_run_status' => 'failed'])->save();

            return ['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 300)];
        } finally {
            $lock->release();
        }
    }

    /**
     * A job may return a string (no findings) or {summary:string, findings:list}. Anything else is stringified.
     *
     * @return array{summary:string,findings:list<array{key:string,count:int,severity:string,fingerprint:string,message:string}>}
     */
    public static function normalise(mixed $result): array
    {
        if (! is_array($result)) {
            return ['summary' => (string) $result, 'findings' => []];
        }

        $findings = [];
        foreach ((array) ($result['findings'] ?? []) as $f) {
            if (! is_array($f) || ! isset($f['key'])) {
                continue;
            }
            $severity = in_array($f['severity'] ?? null, ['info', 'warning', 'critical'], true) ? $f['severity'] : 'info';
            $count = max(0, (int) ($f['count'] ?? 0));
            $findings[] = [
                'key' => (string) $f['key'], 'count' => $count, 'severity' => $severity,
                'fingerprint' => (string) ($f['fingerprint'] ?? $count), 'message' => (string) ($f['message'] ?? ''),
            ];
        }

        return ['summary' => (string) ($result['summary'] ?? ''), 'findings' => $findings];
    }

    /** Sends staff notices for a run's findings. Never throws. */
    public function notifyFindings(PlatformScheduledTask $task, array $findings): int
    {
        if ($findings === []) {
            return 0;
        }

        try {
            return app(SchedulerNotifier::class)->notify($task, $findings);
        } catch (Throwable $e) {
            Log::warning('Scheduler notification failed for '.$task->task_key.': '.$e->getMessage());

            return 0;
        }
    }

    private function isDue(PlatformScheduledTask $task, CarbonImmutable $now): bool
    {
        $from = $task->last_run_at
            ? CarbonImmutable::parse($task->last_run_at)
            : CarbonImmutable::parse($task->created_at ?? $now->subMinute());
        $next = (new CronSchedule($task->scheduleArray()))->nextRunAt($from);

        return $next !== null && $next->lessThanOrEqualTo($now);
    }
}
