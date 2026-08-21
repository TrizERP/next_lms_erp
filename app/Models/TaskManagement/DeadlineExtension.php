<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\DeadlineExtension`.
 *
 * Backs the deadline-extension request/approve flow: an executor requests an
 * extension on a legacy `task` row, an observer approves or rejects it. No
 * soft deletes: source table has no `deleted_at`.
 */
class DeadlineExtension extends Model
{
    use HasFactory;

    protected $table = 'task_deadline_extensions';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'requested_by' => 'integer',
        'requested_date' => 'date',
        'decided_by' => 'integer',
        'decided_on' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
