<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceCompensationRevision`.
 *
 * A proposed / approved CTC revision - the Compensation tab.
 */
class PerformanceCompensationRevision extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_compensation_revisions';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'cycle_id'         => 'integer',
        'review_id'        => 'integer',
        'appraisal_id'     => 'integer',
        'user_id'          => 'integer',
        'department_id'    => 'integer',
        'current_ctc'      => 'float',
        'proposed_ctc'     => 'float',
        'increment_amount' => 'float',
        'increment_pct'    => 'float',
        'effective_date'   => 'date',
        'approver_id'      => 'integer',
        'approved_at'      => 'datetime',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}
