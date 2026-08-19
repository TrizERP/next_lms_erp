<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * Per student/concept spaced-repetition state (Ebbinghaus-style retention
 * and next-review scheduling), updated by updateForgettingCurve() after
 * every practice/diagnostic submission.
 */
class LmsForgettingCurveModel extends Model
{
    protected $table = 'lms_forgetting_curve';

    protected $fillable = [
        'student_id',
        'concept_id',
        'retention_rate',
        'last_reviewed_at',
        'next_review_at',
        'review_interval_days',
        'review_count',
    ];

    protected $casts = [
        'retention_rate' => 'float',
        'last_reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
        'review_interval_days' => 'integer',
        'review_count' => 'integer',
    ];

    public function concept()
    {
        return $this->belongsTo(LmsConceptModel::class, 'concept_id');
    }
}
