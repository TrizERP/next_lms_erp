<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\TaskPriority` (table
 * `task_management_priorities`).
 *
 * A tenant-defined, ordered priority name with an optional SLA in hours.
 * Independent of the legacy `task.task_type` free-string column. No soft
 * deletes: source table has no `deleted_at`.
 */
class TaskPriorityOption extends Model
{
    use HasFactory;

    protected $table = 'task_management_priorities';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'sort_order' => 'integer',
        'sla_hours' => 'integer',
        'active' => 'boolean',
        'created_by' => 'integer',
    ];
}
