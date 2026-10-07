<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/**
 * One approval chain a school has defined against one workflow point.
 *
 * Unlike the other three platform tables, absence here means nothing: a point
 * with no chain simply has no approval, which is the correct default for an
 * action nobody has asked to govern. So this table IS the list — there are no
 * config defaults to merge over, only `suggested_steps` offered when somebody
 * adds the first chain.
 *
 * Nothing in this row is a record of something that happened; it is policy only.
 * That is what makes it safe to edit. Approvals in flight belong in their own
 * table when the engine is built — see the migration.
 */
class PlatformWorkflow extends Model
{
    protected $table = 'platform_workflows';

    protected $fillable = [
        'sub_institute_id',
        'flow_key',
        'module',
        'component',
        'name',
        'description',
        'status',
        'condition',
        'steps',
        'on_reject',
        'notify_requester',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'steps' => 'array',
        'notify_requester' => 'boolean',
    ];

    /** Tenant scope — every read path must go through this. */
    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }

    public function scopeForModule($query, ?string $module)
    {
        return $module ? $query->where('module', $module) : $query;
    }

    public function scopeForComponent($query, ?string $component)
    {
        return $component ? $query->where('component', $component) : $query;
    }

    public function scopeForFlow($query, ?string $flowKey)
    {
        return $flowKey ? $query->where('flow_key', $flowKey) : $query;
    }
}
