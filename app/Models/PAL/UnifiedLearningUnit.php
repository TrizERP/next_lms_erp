<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class UnifiedLearningUnit extends Model
{
    protected $table = 'pal_unified_learning_units';

    protected $fillable = [
        'ulu_id',
        'title',
        'grade',
        'subject',
        'academic_concept',
        'sub_concept',
        'status',
        'difficulty',
        'duration_minutes',
        'language',
        'cultural_context',
        'social_mode',
        'pedagogy_tag',
        'h5p_type',
        'casel_domain',
        'ngss_practice',
        'ncdg_goal',
        'riasec_signal',
        'career_cluster',
        'real_skill_name',
        'mastery_gate',
        'academic_core',
        'sel_layer',
        'stem_layer',
        'career_layer',
        'real_skill',
        'scenario',
        'branches',
        'reflections',
        'delivery',
        'qa_checks',
        'analytics',
        'optimization_flags',
        'cross_domain_links',
        'graph_sync_status',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'grade' => 'integer',
        'difficulty' => 'integer',
        'duration_minutes' => 'integer',
        'mastery_gate' => 'float',
        'academic_core' => 'array',
        'sel_layer' => 'array',
        'stem_layer' => 'array',
        'career_layer' => 'array',
        'real_skill' => 'array',
        'scenario' => 'array',
        'branches' => 'array',
        'reflections' => 'array',
        'delivery' => 'array',
        'qa_checks' => 'array',
        'analytics' => 'array',
        'optimization_flags' => 'array',
        'cross_domain_links' => 'array',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];
}
