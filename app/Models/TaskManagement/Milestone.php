<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Milestone`, verified
 * against the ACTUAL live `task_management_milestones` schema (created by
 * an untracked migration `2026_07_31_204500_create_task_management_workspace_tables`
 * that predates this port and has no corresponding .php file in the repo).
 *
 * Live columns match this port's assumptions exactly: id, project_id,
 * workstream_id (nullable), name, description, target_date, status
 * (varchar(20), default 'UPCOMING'), sub_institute_id, syear, created_by,
 * updated_by, created_at, updated_at. `sub_institute_id` IS present, so
 * this table can be tenant-scoped directly. A dated checkpoint on a project
 * (optionally scoped to a workstream). No soft deletes: source table has no
 * `deleted_at`.
 */
class Milestone extends Model
{
    use HasFactory;

    protected $table = 'task_management_milestones';
    protected $guarded = ['id'];

    protected $casts = [
        'project_id' => 'integer',
        'workstream_id' => 'integer',
        'target_date' => 'date',
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream()
    {
        return $this->belongsTo(Workstream::class, 'workstream_id');
    }
}
