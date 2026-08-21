<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\TaskStatusHistory`,
 * adapted from a g2g_event_store projection into a plain, directly-written
 * log table (see `2026_08_20_100500_create_task_status_history.php`). A
 * later agent's status-change trait inserts one row per transition.
 *
 * `is_reopen` makes a transition INTO an active status FROM a terminal one
 * (COMPLETED, or approve_status='approved') detectable, since the `task` row
 * alone only holds current state.
 *
 * No soft deletes, no `updated_at`: an append-only log.
 */
class TaskStatusHistory extends Model
{
    use HasFactory;

    // No created_at/updated_at columns on this table at all — occurred_at
    // (set explicitly by the writer) is the timestamp of record.
    public $timestamps = false;

    protected $table = 'task_status_history';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'from_terminal' => 'boolean',
        'to_active' => 'boolean',
        'is_reopen' => 'boolean',
        'actor_id' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
