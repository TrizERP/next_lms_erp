<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * One institute's override of one scheduled task.
 *
 * Absence means the task runs on the schedule in config/platform_services.php,
 * so SchedulerController merges rows over the config rather than listing this
 * table. Reset-to-default deletes the row instead of writing today's default
 * into it — see the migration for why that distinction is load-bearing.
 *
 * `next_run_at` is not a column and never will be: it is computed from the five
 * cron fields on read, because a stored one goes stale the moment the schedule
 * is edited.
 */
class PlatformScheduledTask extends Model
{
    protected $table = 'platform_scheduled_tasks';

    protected $fillable = [
        'sub_institute_id',
        'task_key',
        'module',
        'component',
        'minute',
        'hour',
        'day',
        'month',
        'day_of_week',
        'disabled',
        'fail_delay',
        'last_run_at',
        'last_run_status',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'disabled' => 'boolean',
        'fail_delay' => 'integer',
        'last_run_at' => 'datetime',
    ];

    /** Tenant scope — every read path must go through this. */
    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }

    public function scopeForModule($query, ?string $module)
    {
        return $module ? $query->where('module', $module) : $query;
    }

    public function scopeForComponent($query, ?string $component)
    {
        return $component ? $query->where('component', $component) : $query;
    }

    /** The five cron fields as one array, in the order the screen shows them. */
    public function scheduleArray(): array
    {
        return [
            'minute' => (string) $this->minute,
            'hour' => (string) $this->hour,
            'day' => (string) $this->day,
            'month' => (string) $this->month,
            'day_of_week' => (string) $this->day_of_week,
        ];
    }
}
