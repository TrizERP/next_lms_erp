<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
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
    /** @return array<string,callable(PlatformScheduledTask):string> task_key => handler returning a summary */
    private function handlers(): array
    {
        return [
            'reports.scheduled.dispatch' => function (): string {
                $n = app(ReportScheduler::class)->runDue();

                return "Ran {$n} scheduled reports.";
            },
            'communication.campaign.dispatch_queue' => function (): string {
                $n = app(NotificationSender::class)->retryDue();

                return "Retried {$n} queued or failed notifications.";
            },
        ];
    }

    /** @return array{ran:int,failed:int,no_handler:int,escalated:int} */
    public function tick(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $summary = ['ran' => 0, 'failed' => 0, 'no_handler' => 0, 'escalated' => 0];

        // Engine housekeeping that belongs to no single school's task list.
        $summary['escalated'] = app(WorkflowEngine::class)->escalateOverdue();

        foreach (PlatformScheduledTask::query()->where('disabled', false)->get() as $task) {
            if (! $this->isDue($task, $now)) {
                continue;
            }
            $result = $this->run($task, $now);
            $summary[$result['status'] === 'ok' ? 'ran' : ($result['status'] === 'no_handler' ? 'no_handler' : 'failed')]++;
        }

        return $summary;
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
            $message = $handler($task);
            $task->forceFill(['last_run_at' => $now, 'last_run_status' => 'ok'])->save();
            AuditTrail::record((int) $task->sub_institute_id, (string) $task->module, (string) $task->component, 'task_run', [
                'entity_type' => 'scheduled_task', 'entity_id' => $task->task_key, 'after' => ['status' => 'ok', 'message' => $message],
            ]);

            return ['status' => 'ok', 'message' => $message];
        } catch (Throwable $e) {
            $task->forceFill(['last_run_at' => $now, 'last_run_status' => 'failed'])->save();

            return ['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 300)];
        } finally {
            $lock->release();
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
