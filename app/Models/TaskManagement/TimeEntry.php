<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\TimeEntry`.
 *
 * A start/stop time-tracking entry against a legacy `task` row. `minutes` is
 * denormalised on stop so reports never re-derive durations. No soft
 * deletes, no `updated_at`: source table only has `created_at`.
 */
class TimeEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'task_management_time_entries';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'user_id' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minutes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
