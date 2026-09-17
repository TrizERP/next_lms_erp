<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * The concept table the mastery/adaptive-practice/diagnostic/prerequisite-gate
 * pipeline (assessmentQuestionController) is built on. Not to be confused with
 * App\Models\PAL\Concept, which maps to the separate `pal_concepts` table from
 * the newer PAL content model — the two are not the same data and are not
 * currently reconciled; this model intentionally stays scoped to `lms_concept`.
 */
class LmsConceptModel extends Model
{
    protected $table = 'lms_concept';

    /** created_at is set via DB::useCurrent(); there is no updated_at column. */
    public $timestamps = false;

    protected $fillable = [
        'lesson_id',
        'extraction_id',
        'name',
        'description',
        'subject_id',
        'standard_id',
        'chapter_id',
        // The concept -> topic_master link. Live on this estate but created by hand;
        // 2026_09_16_100200 brings it under migration control. It was missing here,
        // so every mass-assigned write silently discarded it - which is why
        // lms:backfill-concept-topics updates the column explicitly.
        'topic_id',
        'sub_institute_id',
        'difficulty_level',
        'bloom_level',
        'pedagogy_tag',
        'mastery_threshold',
        'estimated_mastery_minutes',
        'syear',
    ];

    protected $casts = [
        'topic_id' => 'integer',
        'difficulty_level' => 'integer',
        'mastery_threshold' => 'float',
        'estimated_mastery_minutes' => 'integer',
        'syear' => 'integer',
    ];

    public function masteryRecords()
    {
        return $this->hasMany(LmsConceptMasteryModel::class, 'concept_id');
    }

    public function prerequisites()
    {
        return $this->hasMany(LmsKnowledgeGraphModel::class, 'concept_id');
    }
}
