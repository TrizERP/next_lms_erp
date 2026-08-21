<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Project`.
 *
 * A project (e.g. "Q3 Curriculum Rollout") that groups workstreams,
 * milestones and tasks under one owner, budget and timeline.
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'task_management_projects';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'department_id' => 'integer',
        'sponsor_id' => 'integer',
        'manager_id' => 'integer',
        'start_date' => 'date',
        'due_date' => 'date',
        'budget_estimate' => 'decimal:2',
        'archived_at' => 'datetime',
        'archived_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function members()
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    public function workstreams()
    {
        return $this->hasMany(Workstream::class, 'project_id');
    }

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }
}
