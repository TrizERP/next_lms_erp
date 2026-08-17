<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learner's contribution to a team challenge (§4.3).
 *
 * A teacher may read these per student for intervention. A student may only
 * ever read their own row ("You have contributed to this goal") — never who
 * else has or has not, and never a ranking. GamificationVisibility enforces it.
 */
class TeamChallengeContribution extends Model
{
    protected $table = 'pal_team_challenge_contributions';

    protected $fillable = [
        'challenge_id',
        'learner_id',
        'contributed',
        'contribution_value',
        'first_contributed_at',
        'evaluated_at',
    ];

    protected $casts = [
        'contributed' => 'boolean',
        'contribution_value' => 'float',
        'first_contributed_at' => 'datetime',
        'evaluated_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(TeamChallenge::class, 'challenge_id');
    }
}
