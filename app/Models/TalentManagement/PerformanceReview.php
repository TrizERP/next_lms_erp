<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceReview`.
 *
 * One employee's review inside one cycle.
 *
 * The main table row on the Performance & Rewards Center, plus the 5-step
 * stepper, the Review Board kanban and the Employee Overview sidebar.
 */
class PerformanceReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_reviews';
    protected $guarded = ['id'];

    /** The stage ladder, in order - the sidebar stepper renders exactly this. */
    public const STAGES = ['self_review', 'manager_review', 'calibration', 'final_review', 'completed'];

    protected $casts = [
        'sub_institute_id'       => 'integer',
        'cycle_id'               => 'integer',
        'user_id'                => 'integer',
        'manager_id'             => 'integer',
        'department_id'          => 'integer',
        'self_rating'            => 'float',
        'manager_rating'         => 'float',
        'calibrated_rating'      => 'float',
        'overall_rating'         => 'float',
        'potential_rating'       => 'float',
        'is_draft'               => 'boolean',
        'self_submitted_at'      => 'datetime',
        'manager_submitted_at'   => 'datetime',
        'calibrated_by'          => 'integer',
        'calibrated_at'          => 'datetime',
        'finalized_at'           => 'datetime',
        'due_date'               => 'date',
        'last_reminder_at'       => 'datetime',
        'calibration_session_id' => 'integer',
        'created_by'             => 'integer',
        'updated_by'             => 'integer',
    ];

    public function cycle()
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function goals()
    {
        return $this->hasMany(PerformanceGoal::class, 'review_id');
    }
}
