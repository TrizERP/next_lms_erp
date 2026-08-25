<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\AttachmentVersion`.
 *
 * One uploaded version of a legacy `task` row's attachment. `restored_from`
 * is set when this version was created by restoring an older one. No soft
 * deletes, no `updated_at`: source table only has `created_at`.
 */
class AttachmentVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'task_management_attachment_versions';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'task_id' => 'integer',
        'version' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
        'restored_from' => 'integer',
        'created_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }
}
