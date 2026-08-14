<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * Assessment metadata over lms_question_master (spec §5.1).
 */
class QuestionMetadata extends Model
{
    protected $table = 'pal_question_metadata';

    protected $fillable = [
        'question_id', 'sub_institute_id', 'scope',
        'content_id_ref', 'concept_ref_id', 'chapter_ref_id', 'topic_ref_id', 'sub_concept_ref', 'grade_band', 'stage', 'board',
        'bloom_level', 'practice_level', 'knowledge_type', 'difficulty_1_to_5',
        'irt_a', 'irt_b', 'irt_c', 'discrimination_index', 'avg_time_seconds',
        'first_attempt_correct_rate', 'response_count', 'guessing_vulnerability',
        'psychometrics_derived_at',
        'misconception_tags', 'distractor_rationale',
        'assessment_type', 'format', 'h5p_type', 'pedagogy_mapping_id', 'scaffold_type',
        'response_latency_band', 'visual_dependency', 'offline_compatible',
        'cognitive_load_intrinsic', 'cognitive_load_extraneous', 'cognitive_load_germane',
        'language', 'language_variants_available', 'cultural_context',
        'gender_representation', 'reading_level_fk',
        'skills', 'casel_domain', 'ngss_practice', 'ncdg_goal', 'riasec_signal',
        'gardner_intelligence', 'aptitude_domain', 'nep_vocational_stream', 'nsqf_level',
        'p21_skill', 'hpc_lens_primary',
        'soft_skill_signal', 'career_cluster_signal',
        'quality_status', 'tagged_by', 'confidence', 'content_flag', 'sensitivity_flag',
        'reviewed_by', 'reviewed_at', 'usage_count', 'version', 'ai_rationale',
    ];

    protected $casts = [
        'misconception_tags' => 'array',
        'distractor_rationale' => 'array',
        'language_variants_available' => 'array',
        'skills' => 'array',
        'ai_rationale' => 'array',
        'visual_dependency' => 'boolean',
        'offline_compatible' => 'boolean',
        'sensitivity_flag' => 'boolean',
        'practice_level' => 'integer',
        'difficulty_1_to_5' => 'integer',
        'response_count' => 'integer',
        'usage_count' => 'integer',
        'nsqf_level' => 'integer',
        'irt_a' => 'float',
        'irt_b' => 'float',
        'irt_c' => 'float',
        'discrimination_index' => 'float',
        'first_attempt_correct_rate' => 'float',
        'reading_level_fk' => 'float',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
        'psychometrics_derived_at' => 'datetime',
    ];

    /** Only rows a learner may actually be served — CONTENT LAW C4. */
    public function scopeServable($query)
    {
        return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
    }

    /**
     * Tenant scope, including the shared global vocabulary (sub_institute_id 0).
     */
    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    /**
     * Curriculum scoping with a chapter fallback.
     *
     * concept_id is populated on 47 of 62,209 questions on the live estate, so a
     * concept-only filter selects nothing for practically every learner.
     * chapter_id is populated on 62,206. Prefer concept when it is known, fall
     * back to chapter otherwise — routing sharpens automatically as concept
     * linkage gets backfilled.
     */
    public function scopeForCurriculum($query, ?int $conceptId, ?int $chapterId = null)
    {
        if ($conceptId !== null && $conceptId > 0) {
            return $query->where('concept_ref_id', $conceptId);
        }

        if ($chapterId !== null && $chapterId > 0) {
            return $query->where('chapter_ref_id', $chapterId);
        }

        // Neither key given: match nothing rather than everything. Returning the
        // whole estate here would serve one tenant's items to another learner.
        return $query->whereRaw('1 = 0');
    }

    /**
     * The question itself. There is no Eloquent model over lms_question_master —
     * it is a legacy table used directly by the Blade UI — so this returns the
     * raw row rather than pretending a relation exists.
     */
    public function questionRow(): ?object
    {
        return \Illuminate\Support\Facades\DB::table('lms_question_master')
            ->where('id', $this->question_id)
            ->first();
    }
}
