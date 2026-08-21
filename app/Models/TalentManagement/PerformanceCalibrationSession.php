<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceCalibrationSession`.
 *
 * A calibration session for one cycle (optionally one department).
 *
 * Backs the Calibration tab and the "Calibration Pending" KPI. The per-employee
 * calibrated rating lives on s_performance_reviews.calibrated_rating.
 */
class PerformanceCalibrationSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_calibration_sessions';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id'    => 'integer',
        'cycle_id'            => 'integer',
        'department_id'       => 'integer',
        'facilitator_id'      => 'integer',
        'scheduled_at'        => 'datetime',
        'participant_count'   => 'integer',
        'distribution_target' => 'array',
        'locked_at'           => 'datetime',
        'created_by'          => 'integer',
        'updated_by'          => 'integer',
    ];
}
