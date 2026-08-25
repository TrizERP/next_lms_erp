<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\AuditLog`, adapted from a
 * g2g_event_store projection into a plain, directly-written log table (see
 * `2026_08_20_100300_create_task_management_support_tables.php`). A later
 * agent's status-change trait writes one row per task mutation.
 *
 * No soft deletes, no `updated_at`: an append-only log, matching
 * `s_performance_activity_log`'s convention in the TalentManagement precedent.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'task_management_audit_logs';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'actor_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
