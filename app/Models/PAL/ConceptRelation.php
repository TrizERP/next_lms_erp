<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * REQUIRES / CROSS_CURRICULAR_LINK edges (spec §6.1, §6.2).
 */
class ConceptRelation extends Model
{
    protected $table = 'pal_concept_relations';

    protected $fillable = [
        'from_concept_id', 'to_concept_id', 'relation_type', 'sub_institute_id', 'scope',
        'link_type', 'transfer_direction', 'auto_suggest', 'suggestion_trigger_mastery',
        'mastery_gate', 'quality_status', 'tagged_by',
    ];

    protected $casts = [
        'auto_suggest' => 'boolean',
        'suggestion_trigger_mastery' => 'float',
        'mastery_gate' => 'float',
    ];

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }
}
