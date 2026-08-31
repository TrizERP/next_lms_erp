<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Notification`.
 *
 * A per-user notification, optionally tied to a task. No soft deletes, no
 * `updated_at`: source table only has `created_at` plus `read_at`.
 */
class Notification extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'task_management_notifications';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'user_id' => 'integer',
        'task_id' => 'integer',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
