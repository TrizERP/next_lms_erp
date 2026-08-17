<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * PAL tags for one H5P node (pal_h5p_node_metadata).
 *
 * Association record only — the node's title, body, media and options stay in
 * the source H5P table and are read live by H5PContentRepository. See the
 * migration for why this is not part of pal_content_metadata.
 */
class H5PNodeMetadata extends Model
{
    protected $table = 'pal_h5p_node_metadata';

    protected $fillable = [
        'h5p_type', 'node_id', 'sub_institute_id',
        'chapter_id', 'subject_id', 'standard_id', 'concept_ref_id',
        'pedagogy_tag', 'pedagogy_secondary',
        'bloom_level', 'practice_level', 'difficulty_1_to_5',
        'casel_domain', 'ngss_practice', 'ncdg_goal',
        'music_domain', 'sports_domain', 'finance_level',
        'gardner_intelligence', 'riasec_signal', 'hpc_lens_primary',
        'cultural_context', 'language', 'estimated_duration_minutes', 'engagement_weight',
        'quality_status', 'tagged_by', 'confidence', 'ai_rationale',
        'reviewed_by', 'reviewed_at', 'version',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'sub_institute_id' => 'integer',
        'chapter_id' => 'integer',
        'subject_id' => 'integer',
        'standard_id' => 'integer',
        'concept_ref_id' => 'integer',
        'pedagogy_secondary' => 'array',
        'gardner_intelligence' => 'array',
        'ai_rationale' => 'array',
        'practice_level' => 'integer',
        'difficulty_1_to_5' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'engagement_weight' => 'float',
        'confidence' => 'float',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /** The node key used everywhere else in the module ("type:id"). */
    public function getNodeKeyAttribute(): string
    {
        return "{$this->h5p_type}:{$this->node_id}";
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) ($subInstituteId ?? 0));
    }

    /** Statuses the PAL engine is allowed to serve from. */
    public function scopeServable($query)
    {
        return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
    }
}
