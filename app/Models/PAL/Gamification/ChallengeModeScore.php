<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * A Challenge Mode result (§6).
 *
 * Deliberately a separate table from every mastery signal in PAL: a Challenge
 * Mode score must never move BKT mastery, never feed the practice ladder and
 * never appear in the regular learning path. `week_start` exists so the
 * leaderboard resets weekly and no ranking ever becomes permanent.
 */
class ChallengeModeScore extends Model
{
    protected $table = 'pal_challenge_mode_scores';

    protected $fillable = [
        'learner_id',
        'sub_institute_id',
        'syear',
        'standard_id',
        'division_id',
        'week_start',
        'concept_ref',
        'concept_label',
        'subject_id',
        'score',
        'accuracy_pct',
        'speed_bonus',
        'difficulty_rating',
        'item_count',
        'duration_seconds',
        'payload',
        'submitted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'week_start' => 'date',
        'submitted_at' => 'datetime',
        'score' => 'integer',
        'accuracy_pct' => 'integer',
        'speed_bonus' => 'integer',
        'difficulty_rating' => 'float',
        'item_count' => 'integer',
        'duration_seconds' => 'integer',
    ];
}
