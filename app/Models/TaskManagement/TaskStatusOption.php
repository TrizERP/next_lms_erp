<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\TaskStatus` (table
 * `task_management_statuses`).
 *
 * A tenant-defined display label mapped onto one of the four system status
 * categories (PENDING / IN-PROGRESS / ON HOLD / COMPLETED). The label a task
 * was given is stored on `task.status_label`; the system category itself
 * keeps driving `task.status`, boards, summaries, approvals and transition
 * rules. No soft deletes: source table has no `deleted_at`.
 */
class TaskStatusOption extends Model
{
    use HasFactory;

    protected $table = 'task_management_statuses';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
        'created_by' => 'integer',
    ];
}
