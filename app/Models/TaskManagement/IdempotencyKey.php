<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\IdempotencyKey`.
 *
 * Maps a (tenant, idempotency key) pair to the task it created, so a retried
 * "create task" request never creates a duplicate. No soft deletes, no
 * `updated_at`: source table only has `created_at`.
 */
class IdempotencyKey extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'task_management_idempotency_keys';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
