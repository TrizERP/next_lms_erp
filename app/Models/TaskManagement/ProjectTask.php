<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\ProjectTask`.
 *
 * The join between a project (optionally a workstream) and a legacy `task`
 * row. No soft deletes: source table has no `deleted_at`.
 */
class ProjectTask extends Model
{
    use HasFactory;

    protected $table = 'task_management_project_tasks';
    protected $guarded = ['id'];

    protected $casts = [
        'project_id' => 'integer',
        'workstream_id' => 'integer',
        'task_id' => 'integer',
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'created_by' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workstream()
    {
        return $this->belongsTo(Workstream::class, 'workstream_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
