<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Workstream`.
 *
 * A sub-track of a project (e.g. "Content", "Assessment") that groups
 * project tasks and milestones. No soft deletes: source table has no
 * `deleted_at`.
 */
class Workstream extends Model
{
    use HasFactory;

    protected $table = 'task_management_workstreams';
    protected $guarded = ['id'];

    protected $casts = [
        'project_id' => 'integer',
        'owner_id' => 'integer',
        'start_date' => 'date',
        'due_date' => 'date',
        'sort_order' => 'integer',
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'workstream_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class, 'workstream_id');
    }
}
