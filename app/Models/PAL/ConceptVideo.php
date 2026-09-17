<?php

namespace App\Models\PAL;

use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A video that teaches one concept, with the provenance needed to justify it.
 *
 * Rows arrive as `draft` from the harvester and only reach a student once a
 * human moves them to a servable status — the same CONTENT LAW C4 gate that
 * governs authored overrides and question metadata.
 */
class ConceptVideo extends Model
{
    protected $table = 'pal_concept_video';

    protected $fillable = [
        'concept_id', 'chapter_id', 'sub_institute_id',
        'source', 'provider', 'external_id', 'content_master_id',
        'media_url', 'thumbnail_url', 'title', 'description', 'attribution',
        'duration_seconds', 'language',
        'match_score', 'match_reason', 'rank',
        'quality_status', 'tagged_by',
        'created_by', 'updated_by', 'reviewed_by', 'reviewed_at', 'review_note', 'version',
    ];

    protected $casts = [
        'concept_id' => 'integer',
        'chapter_id' => 'integer',
        'sub_institute_id' => 'integer',
        'content_master_id' => 'integer',
        'duration_seconds' => 'integer',
        'match_score' => 'float',
        'rank' => 'integer',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * The delivery gate. Reads the vocabulary rather than a literal
     * 'approved', so the servable set stays defined in exactly one place.
     */
    public function scopeServable(Builder $query): Builder
    {
        return $query->whereIn(
            'quality_status',
            config('pal_content.servable_statuses', ['approved'])
        );
    }

    /**
     * A tenant's own row and the shared (0) pool. Ordering is applied by the
     * caller, which needs the tenant row to outrank the shared one.
     */
    public function scopeForTenant(Builder $query, ?int $subInstituteId): Builder
    {
        return $query->whereIn('sub_institute_id', array_unique([(int) ($subInstituteId ?? 0), 0]));
    }

    public function isServable(): bool
    {
        return PalVocabulary::isServable($this->quality_status);
    }
}
