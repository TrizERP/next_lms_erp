<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceCycle`.
 *
 * A performance review cycle (FY 2024-25 Annual, H1 Half-Yearly, ...).
 *
 * Drives the header cycle selector, the "Active Review Cycles" KPI and the
 * Cycle Timeline view on the Performance & Rewards Center.
 */
class PerformanceCycle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_cycles';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id'   => 'integer',
        'period_start'       => 'date',
        'period_end'         => 'date',
        'self_review_due'    => 'date',
        'manager_review_due' => 'date',
        'calibration_due'    => 'date',
        'final_review_due'   => 'date',
        'rating_scale_max'   => 'integer',
        'launched_at'        => 'datetime',
        'closed_at'          => 'datetime',
        'created_by'         => 'integer',
        'updated_by'         => 'integer',
    ];

    public function reviews()
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }
}
