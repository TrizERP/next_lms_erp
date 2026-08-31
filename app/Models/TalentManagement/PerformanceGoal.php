<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceGoal`.
 *
 * A weighted, measurable goal (KRA / KPI / OKR) - the Goals tab.
 */
class PerformanceGoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_goals';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'cycle_id'         => 'integer',
        'review_id'        => 'integer',
        'user_id'          => 'integer',
        'department_id'    => 'integer',
        'weightage'        => 'float',
        'start_date'       => 'date',
        'due_date'         => 'date',
        'progress'         => 'integer',
        'self_rating'      => 'float',
        'manager_rating'   => 'float',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    public function review()
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }
}
