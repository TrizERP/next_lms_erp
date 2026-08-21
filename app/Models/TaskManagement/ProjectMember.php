<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\ProjectMember`.
 *
 * A user's membership (and role) on a project. No soft deletes: membership
 * rows are added/removed outright, matching the source table's shape.
 */
class ProjectMember extends Model
{
    use HasFactory;

    protected $table = 'task_management_project_members';
    protected $guarded = ['id'];

    protected $casts = [
        'project_id' => 'integer',
        'user_id' => 'integer',
        'sub_institute_id' => 'integer',
        'syear' => 'integer',
        'created_by' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
