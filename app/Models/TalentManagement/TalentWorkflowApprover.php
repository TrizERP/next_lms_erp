<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;

/**
 * Ported from G2G's `talent_workflow_approvers` table (queried directly via
 * `DB::table` in the source controller, no dedicated model). One row per
 * approver tied to a workflow (role, title, approval type, escalation).
 */
class TalentWorkflowApprover extends Model
{
    protected $table = 'talent_workflow_approvers';
    protected $guarded = ['id'];

    protected $casts = [
        'workflow_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(TalentWorkflow::class, 'workflow_id');
    }
}
