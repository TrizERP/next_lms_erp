<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * Concept enrichment over lms_concept (spec §6.1 :Concept properties).
 */
class ConceptMetadata extends Model
{
    protected $table = 'pal_concept_metadata';

    protected $fillable = [
        'concept_ref_id', 'sub_institute_id', 'scope', 'concept_code',
        'bloom_ceiling', 'practice_ceiling', 'hpc_lens', 'stage_gate',
        'priority_score', 'mastery_gate', 'pedagogy_mapping_id',
        'riasec_primary', 'gardner_primary', 'ngss_practice', 'ncdg_goal',
        'quality_status', 'tagged_by', 'confidence', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'practice_ceiling' => 'integer',
        'priority_score' => 'integer',
        'mastery_gate' => 'float',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    public function prerequisites()
    {
        return $this->hasMany(ConceptRelation::class, 'from_concept_id', 'concept_ref_id')
            ->where('relation_type', 'requires');
    }
}
