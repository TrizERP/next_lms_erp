<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `talent_workflows` table (no dedicated Eloquent model
 * existed in the source — `Api\Talent\AdminWorkflowController` queried the
 * table directly via `DB::table`). One row per configured workflow (e.g.
 * "Job Requisition Approval") shown in the Administration & Governance
 * "Workflows" list/detail screens.
 */
class TalentWorkflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talent_workflows';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function stages()
    {
        return $this->hasMany(TalentWorkflowStage::class, 'workflow_id');
    }

    public function approvers()
    {
        return $this->hasMany(TalentWorkflowApprover::class, 'workflow_id');
    }
}
