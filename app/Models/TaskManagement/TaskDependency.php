<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\TaskDependency`, verified
 * against the ACTUAL live `task_management_dependencies` schema (created by
 * an untracked migration `2026_07_31_204500_create_task_management_workspace_tables`
 * that predates this port and has no corresponding .php file in the repo).
 *
 * Live columns match this port's assumptions exactly: id,
 * predecessor_task_id, successor_task_id, dependency_type (varchar(2),
 * default 'FS'), lag_days, notes, project_id (nullable), workstream_id
 * (nullable), sub_institute_id, syear, created_by, updated_by, created_at,
 * updated_at. Both predecessor/successor loosely reference `task.ID`,
 * matching the legacy table's convention of no foreign key constraints.
 * `sub_institute_id` IS present here (unlike `task_management_comments`),
 * so this table can be tenant-scoped directly. No soft deletes: source
 * table has no `deleted_at`.
 */
class TaskDependency extends Model
{
    use HasFactory;

    protected $table = 'task_management_dependencies';
    protected $guarded = ['id'];

    protected $casts = [
        'predecessor_task_id' => 'integer',
        'successor_task_id' => 'integer',
        'lag_days' => 'integer',
        'project_id' => 'integer',
        'workstream_id' => 'integer',
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function predecessorTask()
    {
        return $this->belongsTo(Task::class, 'predecessor_task_id', 'ID');
    }

    public function successorTask()
    {
        return $this->belongsTo(Task::class, 'successor_task_id', 'ID');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream()
    {
        return $this->belongsTo(Workstream::class, 'workstream_id');
    }
}
