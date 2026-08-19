<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * The learner's streak head (§7).
 *
 * `grace_used_on` implements the one forgiven day per week: illness, school
 * events and family situations must not carry a penalty.
 */
class LearnerStreak extends Model
{
    protected $table = 'pal_learner_streaks';

    protected $fillable = [
        'learner_id',
        'current_streak',
        'current_started_on',
        'longest_streak',
        'longest_streak_ended_on',
        'last_active_date',
        'grace_used_on',
        'total_active_days',
        'recomputed_at',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_active_days' => 'integer',
        'current_started_on' => 'date',
        'longest_streak_ended_on' => 'date',
        'last_active_date' => 'date',
        'grace_used_on' => 'date',
        'recomputed_at' => 'datetime',
    ];
}
