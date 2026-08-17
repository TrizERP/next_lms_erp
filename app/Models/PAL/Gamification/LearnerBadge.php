<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One badge held by one learner (§3.2 "Award to student").
 *
 * Badges never expire and are never deleted. A teacher who judges that a badge
 * was gamed (§10.3) sets `revoked_at` — the row survives as audit, and the
 * student-facing collection simply stops counting it.
 *
 * `scope_key` is '' for a global badge, or the concept / subject key for the
 * scoped ones, so "Subject champion" can be held once per subject.
 */
class LearnerBadge extends Model
{
    protected $table = 'pal_learner_badges';

    protected $fillable = [
        'learner_id',
        'badge_id',
        'scope_key',
        'awarded_at',
        'context',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $casts = [
        'context' => 'array',
        'awarded_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'badge_id', 'badge_id');
    }

    public function scopeHeld($query)
    {
        return $query->whereNull('revoked_at');
    }
}
