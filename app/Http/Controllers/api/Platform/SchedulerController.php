<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\CronSchedule;
use App\Services\Platform\PlatformRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The Scheduler service — the recurring work each component owns, and when it
 * runs for this institute.
 *
 * WHAT THIS ENDPOINT DOES AND DOES NOT DO. It configures. It does not dispatch:
 * nothing here starts a job, and `last_run_at` / `last_run_status` are written by
 * whatever runs the work, never by this screen. Keeping the two apart is what
 * makes the configuration safe to edit while jobs are running.
 *
 * DEFAULTS ARE MERGED, NOT SEEDED. A task nobody has touched has no row and runs
 * on the schedule in config/platform_services.php. `customised` on each row says
 * which is which, and reset-to-default DELETES the row rather than writing
 * today's default into it — so "default" keeps meaning "whatever the product
 * currently ships" rather than "whatever it shipped the day somebody pressed
 * reset".
 *
 * NEXT RUN IS COMPUTED ON READ. A stored next-run goes stale the moment anyone
 * edits the schedule, and a stale timestamp on an administration screen is worse
 * than none, because it looks like an answer.
 */
class SchedulerController extends PlatformController
{
    /**
     * GET /api/platform/scheduler?module=&component=
     *
     * Every scheduled task in scope, with this institute's overrides applied, the
     * schedule described in words, and the next run worked out.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$module, $component] = $this->readScope($request);
        $tasks = $this->tasksFor($tenantId, $module, $component);

        return $this->ok([
            'tasks' => $tasks,
            'summary' => $this->summarise($tasks),
        ]);
    }

    /**
     * PUT /api/platform/scheduler
     *
     * Body: {"task_key":"fees.defaulter.overdue_scan",
     *        "schedule":{"hour":"2"},"disabled":false,"fail_delay":0}
     *   or: {"task_key":"...","reset_to_default":true}
     *
     * ONE TASK AT A TIME, unlike the notification matrix. A schedule is edited
     * deliberately, one row at a time, and each edit is worth its own audit entry
     * — batching them would blur who changed which schedule when.
     *
     * A PARTIAL SCHEDULE IS PARTIAL: a body naming only `hour` keeps the other
     * four fields as they were.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $taskKey = trim((string) $request->input('task_key', ''));
        $definition = $this->registry->task($taskKey);
        if ($definition === null) {
            return $this->fail('"'.($taskKey ?: '(none)').'" is not a known scheduled task.', 404);
        }

        $existing = PlatformScheduledTask::forTenant($tenantId)->where('task_key', $taskKey)->first();

        // Reset means "forget that this institute ever disagreed" — so the row
        // goes, and the registry answers again from now on, including any future
        // improvement to the default.
        if ($request->boolean('reset_to_default')) {
            $existing?->delete();

            return $this->ok(['task' => $this->taskRow($taskKey, $definition, null)]);
        }

        $fallback = $existing
            ? $existing->scheduleArray()
            : $this->registry->defaultScheduleFor($taskKey);

        $scheduleInput = $request->input('schedule');
        if ($scheduleInput !== null && ! is_array($scheduleInput)) {
            return $this->fail('schedule must be an object of the five cron fields.');
        }

        $schedule = CronSchedule::normalise((array) ($scheduleInput ?? []), $fallback);

        // The screen validates as the operator types; this is the check that
        // decides. A row in this table always parses.
        if ($problem = CronSchedule::problem($schedule)) {
            return $this->fail($problem, 422);
        }

        $disabled = $existing
            ? (bool) $existing->disabled
            : (bool) ($definition['disabled_by_default'] ?? false);
        if ($request->has('disabled')) {
            if (! is_bool($request->input('disabled'))) {
                return $this->fail('disabled must be true or false.');
            }
            $disabled = $request->boolean('disabled');
        }

        $failDelay = $existing ? (int) $existing->fail_delay : 0;
        if ($request->has('fail_delay')) {
            $value = $request->input('fail_delay');
            if (! is_numeric($value) || (int) $value < 0) {
                return $this->fail('fail_delay must be a whole number of minutes, 0 or more.');
            }
            // A day. Past that the back-off has stopped being a retry policy and
            // become a way to forget that a task is broken.
            if ((int) $value > 1440) {
                return $this->fail('fail_delay cannot be more than 1440 minutes. Disable the task instead.');
            }
            $failDelay = (int) $value;
        }

        $row = PlatformScheduledTask::updateOrCreate(
            ['sub_institute_id' => $tenantId, 'task_key' => $taskKey],
            array_merge($schedule, [
                'module' => PlatformRegistry::moduleOf($taskKey),
                'component' => PlatformRegistry::componentOf($taskKey),
                'disabled' => $disabled,
                'fail_delay' => $failDelay,
                'updated_by' => $this->actorLabel($request),
            ])
        );

        return $this->ok(['task' => $this->taskRow($taskKey, $definition, $row->refresh())]);
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    private function tasksFor(int $tenantId, ?string $module, ?string $component): array
    {
        $saved = PlatformScheduledTask::forTenant($tenantId)
            ->forModule($module)
            ->forComponent($component)
            ->get()
            ->keyBy('task_key');

        $rows = [];
        foreach ($this->registry->scope($this->registry->tasks(), $module, $component) as $key => $definition) {
            $rows[] = $this->taskRow($key, $definition, $saved->get($key));
        }

        return $rows;
    }

    /**
     * One task: the registry entry, the institute's override if any, and the
     * three things only this layer can work out — the sentence, the raw
     * expression, and the next run.
     *
     * @param  array<string,mixed>  $definition
     */
    private function taskRow(string $key, array $definition, ?PlatformScheduledTask $row): array
    {
        $default = $this->registry->defaultScheduleFor($key);
        $schedule = $row ? $row->scheduleArray() : $default;
        $componentKey = PlatformRegistry::componentOf($key);

        // A schedule that cannot be parsed has no sentence and no next run. That
        // only happens to a row written before this validation existed, or edited
        // in the database by hand; saying so beats rendering "Invalid Date".
        $describes = null;
        $nextRun = null;
        try {
            $cron = new CronSchedule($schedule);
            $describes = $cron->describe();
            $nextRun = $row && $row->disabled
                // A disabled task has no next run. Showing one would say it is
                // about to happen.
                ? null
                : $cron->nextRunAt(CarbonImmutable::now())?->toIso8601String();
        } catch (InvalidArgumentException $e) {
            $describes = $e->getMessage();
        }

        $disabled = $row
            ? (bool) $row->disabled
            : (bool) ($definition['disabled_by_default'] ?? false);

        return [
            'key' => $key,
            'module' => PlatformRegistry::moduleOf($key),
            'component' => $componentKey,
            'component_label' => $this->registry->components()[$componentKey]['label'] ?? $componentKey,
            'label' => $definition['label'] ?? $key,
            'description' => $definition['description'] ?? '',
            'schedule' => $schedule,
            'default_schedule' => $default,
            'expression' => implode(' ', array_values($schedule)),
            'describes' => $describes,
            'disabled' => $disabled,
            'disabled_by_default' => (bool) ($definition['disabled_by_default'] ?? false),
            'fail_delay' => $row ? (int) $row->fail_delay : 0,
            'last_run_at' => $row?->last_run_at?->toIso8601String(),
            'last_run_status' => $row?->last_run_status,
            'next_run_at' => $nextRun,
            'customised' => (bool) $row && ! CronSchedule::equal($schedule, $default),
            'overridden' => (bool) $row,
            'updated_at' => $row?->updated_at?->toIso8601String(),
            'updated_by' => $row?->updated_by,
        ];
    }

    /** @param list<array<string,mixed>> $tasks */
    private function summarise(array $tasks): array
    {
        $enabled = 0;
        $customised = 0;
        $failing = 0;

        foreach ($tasks as $task) {
            if (! $task['disabled']) {
                $enabled++;
            }
            if ($task['customised']) {
                $customised++;
            }
            if ($task['last_run_status'] === 'failed') {
                $failing++;
            }
        }

        return [
            'total' => count($tasks),
            'enabled' => $enabled,
            'disabled' => count($tasks) - $enabled,
            'customised' => $customised,
            'failing' => $failing,
        ];
    }
}
