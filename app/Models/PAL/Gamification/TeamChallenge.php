<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teacher-set class challenge (§4).
 *
 * The whole class wins or loses together, which is the point: aggregate
 * progress means a struggling learner's improvement still counts and no
 * individual is exposed as the weakest. Progress is NOT stored on this row —
 * TeamChallengeService recomputes it from live class data on every read, so the
 * bar can never drift from reality.
 */
class TeamChallenge extends Model
{
    protected $table = 'pal_team_challenges';

    protected $fillable = [
        'sub_institute_id',
        'syear',
        'grade_id',
        'standard_id',
        'division_id',
        'teacher_id',
        'type',
        'title',
        'description',
        'subject_id',
        'concept_ref',
        'concept_label',
        'target_metric',
        'target_value',
        'target_tier',
        'baseline_value',
        'deadline',
        'reward_title',
        'reward_description',
        'reward_content_id',
        'reward_approved',
        'status',
        'completed_at',
        'ended_at',
        'ended_by',
        'ended_reason',
    ];

    protected $casts = [
        'target_value' => 'float',
        'baseline_value' => 'float',
        'reward_approved' => 'boolean',
        'deadline' => 'date',
        'completed_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function contributions(): HasMany
    {
        return $this->hasMany(TeamChallengeContribution::class, 'challenge_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
