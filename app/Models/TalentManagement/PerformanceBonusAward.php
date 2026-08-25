<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceBonusAward`.
 *
 * A bonus award (performance / retention / spot / ...) - the Bonus tab.
 */
class PerformanceBonusAward extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_bonus_awards';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'cycle_id'         => 'integer',
        'review_id'        => 'integer',
        'appraisal_id'     => 'integer',
        'user_id'          => 'integer',
        'department_id'    => 'integer',
        'amount'           => 'float',
        'pct_of_ctc'       => 'float',
        'payout_date'      => 'date',
        'approver_id'      => 'integer',
        'approved_at'      => 'datetime',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}
