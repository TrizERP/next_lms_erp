<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The badge catalogue row (PAL V4 Gamification §3.2).
 *
 * A badge is a RULE, not an achievement: `trigger_type` + `trigger_config`
 * name the evaluator BadgeService runs against a learner's real signal pack.
 * Rows are mirrored from config/pal_gamification.php by the module migration,
 * so an institute can retire a badge (status = 'retired') without a code change.
 */
class Badge extends Model
{
    protected $table = 'pal_badges';

    protected $fillable = [
        'badge_id',
        'name',
        'category',
        'description',
        'student_message',
        'hpc_domain',
        'casel_domain',
        'ncdg_goal',
        'rarity',
        'hpc_evidence_weight',
        'scope',
        'trigger_type',
        'trigger_config',
        'challenge_mode_only',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'challenge_mode_only' => 'boolean',
        'hpc_evidence_weight' => 'float',
        'sort_order' => 'integer',
    ];

    public function awards(): HasMany
    {
        return $this->hasMany(LearnerBadge::class, 'badge_id', 'badge_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
