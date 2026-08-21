<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceAppraisal`.
 *
 * The appraisal decision (final rating + promotion recommendation) - Appraisals tab.
 */
class PerformanceAppraisal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_appraisals';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'cycle_id'         => 'integer',
        'review_id'        => 'integer',
        'user_id'          => 'integer',
        'department_id'    => 'integer',
        'final_rating'     => 'float',
        'effective_date'   => 'date',
        'approver_id'      => 'integer',
        'approved_at'      => 'datetime',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }
}
