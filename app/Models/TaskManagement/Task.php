<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from hp_erp's `App\Models\Task`.
 *
 * The legacy `task` table (`database/migrations/2023_03_05_115658_create_task_table.php`
 * in this repo) predates Task Management and uses its own uppercase column
 * set (TASK_TITLE, STATUS, TASK_ALLOCATED_TO, SYEAR, ...), extended here by
 * `2026_08_20_100000_add_planning_columns_to_task_table.php` and
 * `2026_08_20_100400_create_task_option_sets.php` with the lowercase
 * planning/status_label columns Task Management reads and writes.
 *
 * No existing model in this repo (`App\Models\front_desk\*` or elsewhere)
 * covers the `task` table, so this is a fresh model in the TaskManagement
 * namespace rather than a reuse of a front_desk-coupled class. The table has
 * no `deleted_at`/`updated_at` columns (only `CREATED_ON`), so no
 * SoftDeletes/timestamps here — created_at/updated_at management is left off
 * to match the table's actual shape.
 */
class Task extends Model
{
    use HasFactory;

    protected $table = 'task';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = ['ID'];

    protected $casts = [
        'TASK_DATE' => 'datetime',
        'TASK_ALLOCATED' => 'integer',
        'TASK_ALLOCATED_TO' => 'integer',
        'CREATED_ON' => 'datetime',
        'CREATED_BY' => 'integer',
        'SYEAR' => 'integer',
        'MARKING_PERIOD_ID' => 'integer',
        'sub_institute_id' => 'integer',
        'approved_by' => 'integer',
        'approved_on' => 'datetime',
        'planned_start_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'remaining_hours' => 'decimal:2',
    ];

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'task_id', 'ID');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'task_id', 'ID');
    }

    public function subtasks()
    {
        return $this->hasMany(Subtask::class, 'task_id', 'ID');
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class, 'task_id', 'ID');
    }

    public function attachmentVersions()
    {
        return $this->hasMany(AttachmentVersion::class, 'task_id', 'ID');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'task_id', 'ID');
    }

    public function statusHistory()
    {
        return $this->hasMany(TaskStatusHistory::class, 'task_id', 'ID');
    }

    public function deadlineExtensions()
    {
        return $this->hasMany(DeadlineExtension::class, 'task_id', 'ID');
    }

    public function recurrence()
    {
        return $this->hasOne(Recurrence::class, 'task_id', 'ID');
    }
}
