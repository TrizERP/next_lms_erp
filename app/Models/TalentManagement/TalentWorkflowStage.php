<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;

/**
 * Ported from G2G's `talent_workflow_stages` table (queried directly via
 * `DB::table` in the source controller, no dedicated model). One row per
 * ordered stage of a workflow (e.g. step 1 "Requisition Raised").
 */
class TalentWorkflowStage extends Model
{
    protected $table = 'talent_workflow_stages';
    protected $guarded = ['id'];

    protected $casts = [
        'workflow_id' => 'integer',
        'step' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(TalentWorkflow::class, 'workflow_id');
    }
}
