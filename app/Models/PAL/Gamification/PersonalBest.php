<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * A learner's current record on one metric (§2.2).
 *
 * `previous_value` is kept alongside the best precisely because §2.3 requires
 * the "up from X" framing — the only comparison the system is allowed to make
 * is against the learner's own earlier self.
 */
class PersonalBest extends Model
{
    protected $table = 'pal_personal_bests';

    protected $fillable = [
        'learner_id',
        'metric_key',
        'scope_type',
        'scope_ref',
        'scope_label',
        'best_value',
        'best_achieved_at',
        'previous_value',
        'previous_achieved_at',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'best_value' => 'float',
        'previous_value' => 'float',
        'best_achieved_at' => 'datetime',
        'previous_achieved_at' => 'datetime',
    ];
}
