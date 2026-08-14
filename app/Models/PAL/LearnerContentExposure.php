<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * The learner shown-set behind CONTENT LAW C7 (variants re-route, never re-teach).
 */
class LearnerContentExposure extends Model
{
    protected $table = 'pal_learner_content_exposure';

    protected $fillable = [
        'learner_id', 'sub_institute_id', 'concept_ref_id', 'chapter_ref_id', 'content_type',
        'content_master_id', 'corrective_id', 'question_id', 'content_id_ref',
        'format', 'variant_number', 'practice_level', 'reason', 'misconception_tag',
        'session_id', 'resolved', 'served_at',
    ];

    protected $casts = [
        'variant_number' => 'integer',
        'practice_level' => 'integer',
        'resolved' => 'boolean',
        'served_at' => 'datetime',
    ];

    public function scopeForLearnerConcept($query, int $learnerId, ?int $conceptId)
    {
        $query->where('learner_id', $learnerId);

        return $conceptId === null ? $query : $query->where('concept_ref_id', $conceptId);
    }
}
