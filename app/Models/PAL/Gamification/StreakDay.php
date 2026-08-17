<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * One calendar day of measured productive engagement (§7.1).
 *
 * `qualified` is false for a day the learner merely opened the app: the rules
 * require a completed learning cell, three spaced-review items, a peer teaching
 * session or a team-challenge contribution, plus the minimum productive minutes.
 */
class StreakDay extends Model
{
    protected $table = 'pal_streak_days';

    protected $fillable = [
        'learner_id',
        'activity_date',
        'productive_minutes',
        'qualifying_events',
        'sources',
        'qualified',
    ];

    protected $casts = [
        'sources' => 'array',
        'qualified' => 'boolean',
        'activity_date' => 'date',
        'productive_minutes' => 'integer',
        'qualifying_events' => 'integer',
    ];
}
