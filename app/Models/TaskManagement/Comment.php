<?php

namespace App\Models\TaskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from hp_erp's `App\Models\TaskManagement\Comment`, adjusted to the
 * ACTUAL live `task_management_comments` schema (created by an untracked
 * migration `2026_07_31_204500_create_task_management_workspace_tables`
 * that predates this port and has no corresponding .php file in the repo).
 *
 * Live columns: id, task_id, user_id, content, created_by, created_at,
 * updated_at. There is NO `sub_institute_id` column and NO `author_id`
 * column on this table — the author is tracked via `user_id` (and
 * `created_by`, which is redundant with `user_id` on this table but kept
 * separately since both exist in the live schema). No soft deletes: table
 * has no `deleted_at`.
 *
 * Tenant scoping: this table cannot be scoped by a direct
 * `sub_institute_id` column (it doesn't have one). Scope by joining through
 * the parent task instead, e.g.:
 *   Comment::whereHas('task', fn ($q) => $q->where('sub_institute_id', $subInstituteId))
 * or a join on `task_id` = `task.ID` filtering `task.sub_institute_id`.
 */
class Comment extends Model
{
    use HasFactory;

    protected $table = 'task_management_comments';
    protected $guarded = ['id'];

    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'ID');
    }

    public function author()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
