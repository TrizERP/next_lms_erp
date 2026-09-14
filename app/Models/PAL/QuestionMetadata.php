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
        'question_id', 'sub_institute_id', 'curriculum_version_id', 'scope',
        'content_id_ref', 'concept_ref_id', 'node_id', 'chapter_ref_id', 'topic_ref_id', 'sub_concept_ref', 'grade_band', 'stage', 'board',
        'blueprint_category', 'marks', 'learning_outcome_ref',
        'bloom_level', 'practice_level', 'knowledge_type', 'item_type', 'difficulty_1_to_5',
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
        'marks' => 'float',
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

    /** PAL consumer — approved items available to adaptive selection. */
    public function scopeForPal($query)
    {
        return $query->servable();
    }

    /** Examination consumer — approved items fit for a board paper. */
    public function scopeForExamination($query)
    {
        return $query
            ->servable()
            ->whereNotNull('board')
            ->where('board', '!=', '')
            ->whereNotNull('blueprint_category')
            ->whereNotNull('marks')
            ->where('marks', '>', 0.0);
    }

    /**
     * Items whose psychometrics were genuinely derived from responses, not
     * merely approved by an author.
     *
     * servable() is an editorial gate — it says a human published the item. It
     * does not say the item discriminates between learners, which is the one
     * property a diagnostic actually depends on. Both gates below mirror
     * DeriveIrtCommand's own thresholds so "calibrated" means the same thing
     * wherever it is asserted:
     *
     *   - response_count >= irt.min_responses     enough data to derive from
     *   - discrimination_index >= approve_above_  the item separates learners
     *
     * psychometrics_derived_at is required too, so a hand-typed
     * discrimination_index can never pass for a derived one.
     */
    public function scopeCalibrated($query)
    {
        $cfg = config('pal_content.irt');

        return $query
            ->whereNotNull('psychometrics_derived_at')
            ->whereNotNull('discrimination_index')
            ->where('discrimination_index', '>=', $cfg['approve_above_discrimination'])
            ->where('response_count', '>=', $cfg['min_responses']);
    }

    // ── Dual fitness for purpose ─────────────────────────────────────────────
    //
    // One item, two INDEPENDENT fitness criteria. "Calibrated for PAL" and
    // "compliant for a board paper" measure different things, so an item can
    // satisfy either, both or neither:
    //
    //   calibrated, not board-fit  — strong discrimination, no blueprint category
    //   board-fit, not calibrated  — a valid 3-mark short answer nobody has sat
    //
    // Collapsing them into one "quality" number is what makes a single Question
    // Intelligence Engine look impossible; kept apart, one engine serves both
    // consumers because each asks its own question of the same row.

    /**
     * The PAL half: can this item measure a learner?
     *
     * @return array<string, mixed>
     */
    public function palCalibration(): array
    {
        return [
            'difficulty_1_to_5' => $this->difficulty_1_to_5,
            'discrimination_index' => $this->discrimination_index,
            'irt_a' => $this->irt_a,
            'irt_b' => $this->irt_b,
            'irt_c' => $this->irt_c,
            'response_count' => $this->response_count,
            'psychometrics_derived_at' => $this->psychometrics_derived_at,
            'misconception_tags' => $this->misconception_tags,
            'fit' => $this->isFitForPalDiagnostic(),
        ];
    }

    /**
     * The board half: can this item be placed in a paper?
     *
     * @return array<string, mixed>
     */
    public function boardCompliance(): array
    {
        return [
            'board' => $this->board,
            'grade_band' => $this->grade_band,
            'stage' => $this->stage,
            'blueprint_category' => $this->blueprint_category,
            'marks' => $this->marks,
            'learning_outcome_ref' => $this->learning_outcome_ref,
            'fit' => $this->isFitForBoardExam(),
        ];
    }

    /**
     * Fit to serve in PAL's adaptive diagnostic.
     *
     * MUST agree with scopeCalibrated() on any given row. They cannot share an
     * implementation — one is SQL, one is PHP over a loaded model — so the
     * thresholds are read from the same config in both, and a test asserts the
     * two answer identically. If you change one, change the other.
     */
    public function isFitForPalDiagnostic(): bool
    {
        $cfg = config('pal_content.irt');

        return $this->psychometrics_derived_at !== null
            && $this->discrimination_index !== null
            && (float) $this->discrimination_index >= (float) $cfg['approve_above_discrimination']
            && (int) $this->response_count >= (int) $cfg['min_responses'];
    }

    /**
     * Fit to be placed in a board paper.
     *
     * Deliberately says nothing about psychometrics: a board paper is assembled
     * from a blueprint, and an uncalibrated item is a perfectly valid 3-mark
     * short answer. Marks must be positive — a zero-mark item occupies a
     * blueprint slot while contributing nothing to the total.
     */
    public function isFitForBoardExam(): bool
    {
        return $this->board !== null
            && $this->board !== ''
            && $this->blueprint_category !== null
            && $this->marks !== null
            && (float) $this->marks > 0.0;
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

    /** Items tagged against one specific K/A/S node. */
    public function scopeForNode($query, int $nodeId)
    {
        return $query->where('node_id', $nodeId);
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
