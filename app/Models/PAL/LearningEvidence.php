<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class LearningEvidence extends Model
{
    protected $table = 'pal_learning_evidence';

    protected $fillable = [
        'learner_id',
        'content_id',
        'concept_id',
        'session_id',
        'pedagogy_tag',
        'h5p_type',
        'evidence_type',
        'framework_tags',
        'score',
        'duration_seconds',
        'completion',
        'evidence_source',
        'context_data',
        'recorded_at',
    ];

    protected $casts = [
        'framework_tags' => 'array',
        'context_data' => 'array',
        'score' => 'float',
        'duration_seconds' => 'integer',
        'completion' => 'boolean',
        'recorded_at' => 'datetime',
    ];
}
