<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/** One "you broke your own record" moment — the personal-best history feed (§2.3). */
class PersonalBestEvent extends Model
{
    protected $table = 'pal_personal_best_events';

    protected $fillable = [
        'learner_id',
        'metric_key',
        'scope_type',
        'scope_ref',
        'scope_label',
        'value',
        'previous_value',
        'improvement_pct',
        'achieved_at',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'value' => 'float',
        'previous_value' => 'float',
        'improvement_pct' => 'float',
        'achieved_at' => 'datetime',
    ];
}
