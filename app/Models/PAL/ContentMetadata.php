<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * Content metadata over content_master (spec §2.2).
 */
class ContentMetadata extends Model
{
    protected $table = 'pal_content_metadata';

    protected $fillable = [
        'content_master_id', 'sub_institute_id', 'scope',
        'content_id_ref', 'concept_ref_id', 'chapter_ref_id', 'topic_ref_id', 'sub_concept_ref',
        'content_type', 'variant_number', 'format',
        'language', 'language_variants_available', 'grade_band', 'stage',
        'bloom_level_served', 'practice_level', 'difficulty_1_to_5',
        'pedagogy_mapping_id', 'pedagogy_secondary', 'cultural_context',
        'reading_level_fk', 'estimated_duration_minutes',
        'visual_dependency', 'offline_compatible',
        'h5p_type', 'h5p_content_id', 'video_url',
        'has_alt_text', 'has_transcript', 'wcag_level', 'screen_reader_compatible',
        'casel_domain', 'ngss_practice', 'ncdg_goal', 'gardner_intelligence',
        'riasec_signal', 'hpc_lens_primary',
        'quality_status', 'tagged_by', 'confidence', 'reviewed_by', 'reviewed_at',
        'last_reviewed', 'usage_count', 'avg_mastery_post', 'confusion_flag_count',
        'discrimination_index', 'content_flag', 'version', 'ai_rationale',
    ];

    protected $casts = [
        'language_variants_available' => 'array',
        'pedagogy_secondary' => 'array',
        'gardner_intelligence' => 'array',
        'ai_rationale' => 'array',
        'visual_dependency' => 'boolean',
        'offline_compatible' => 'boolean',
        'has_alt_text' => 'boolean',
        'has_transcript' => 'boolean',
        'screen_reader_compatible' => 'boolean',
        'variant_number' => 'integer',
        'practice_level' => 'integer',
        'difficulty_1_to_5' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'usage_count' => 'integer',
        'confusion_flag_count' => 'integer',
        'reading_level_fk' => 'float',
        'avg_mastery_post' => 'float',
        'discrimination_index' => 'float',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
        'last_reviewed' => 'datetime',
    ];

    public function scopeServable($query)
    {
        return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
    }

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
     * concept_id is populated on 1 of 31,362 content_master rows on the live
     * estate; chapter_id on 31,357. A concept-only filter would route nothing,
     * so chapter is the working key until concept linkage is backfilled.
     */
    public function scopeForCurriculum($query, ?int $conceptId, ?int $chapterId = null)
    {
        if ($conceptId !== null && $conceptId > 0) {
            return $query->where('concept_ref_id', $conceptId);
        }

        if ($chapterId !== null && $chapterId > 0) {
            return $query->where('chapter_ref_id', $chapterId);
        }

        // Match nothing rather than the whole estate — a missing key must not
        // silently widen the selection across tenants.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Underperforming content — spec §7.3 monitoring query.
     */
    public function scopeUnderperforming($query)
    {
        $m = config('pal_content.monitoring');

        return $query->where('usage_count', '>', $m['min_usage_for_review'])
            ->where('avg_mastery_post', '<', $m['underperforming_mastery_below'])
            ->where('quality_status', 'approved');
    }
}
