<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Recurrence`.
 *
 * The recurrence rule for a legacy `task` row (one rule per task — `task_id`
 * is unique). No soft deletes: source table has no `deleted_at`.
 */
class Recurrence extends Model
{
    use HasFactory;

    protected $table = 'task_management_recurrences';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'interval' => 'integer',
        'until' => 'date',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
