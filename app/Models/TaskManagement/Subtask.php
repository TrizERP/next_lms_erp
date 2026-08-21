<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Subtask`.
 *
 * A checklist item under a legacy `task` row. No soft deletes: source table
 * has no `deleted_at`.
 */
class Subtask extends Model
{
    use HasFactory;

    protected $table = 'task_management_subtasks';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'is_done' => 'boolean',
        'sort_order' => 'integer',
        'created_by' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
