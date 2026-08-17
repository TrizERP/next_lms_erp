<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * A learner's Challenge Mode consent (§6.1).
 *
 * Absence of a row means "not opted in" — competitive comparison is never the
 * default. Opting out removes the learner's scores from every leaderboard
 * display immediately (§6.2 reset rule).
 */
class ChallengeModeOptIn extends Model
{
    protected $table = 'pal_challenge_mode_optins';

    protected $fillable = [
        'learner_id',
        'opted_in',
        'opted_in_at',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
    ];
}
