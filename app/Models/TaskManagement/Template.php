<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Template`.
 *
 * A reusable set of task fields (`payload`, stored as longText JSON) that
 * pre-fills a new task. No soft deletes: source table has no `deleted_at`.
 * Not tied to a specific task, so no `task()` relationship.
 */
class Template extends Model
{
    use HasFactory;

    protected $table = 'task_management_templates';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'created_by' => 'integer',
    ];
}
