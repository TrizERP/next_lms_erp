<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * The learner's own Career Quest declarations (§5.4).
 *
 * Only what the learner CHOSE is stored: the non-binding interest declaration
 * and, later, a chosen pathway. Stage, RIASEC profile, pathway ranking and
 * skill progress are all recomputed from evidence by CareerQuestService, so a
 * stale row can never contradict what the learner has actually done.
 */
class CareerQuestState extends Model
{
    protected $table = 'pal_career_quest_states';

    protected $fillable = [
        'learner_id',
        'interest_declaration',
        'declared_at',
        'chosen_primary_pathway',
        'chosen_secondary_pathway',
        'skills_target_primary',
        'report_generated_at',
        'report_snapshot',
    ];

    protected $casts = [
        'interest_declaration' => 'array',
        'report_snapshot' => 'array',
        'declared_at' => 'datetime',
        'report_generated_at' => 'datetime',
        'skills_target_primary' => 'integer',
    ];
}
