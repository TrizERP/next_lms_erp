<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * Prerequisite edges between curriculum nodes that are NOT concepts.
 *
 * The sibling of App\Models\PAL\ConceptRelation: that one owns concept -> concept
 * edges in `pal_concept_relations`, this one owns topic/unit/chapter edges in
 * `pal_learning_relations`. The Coherence Map reads both and presents one edge list.
 *
 * Direction follows `pal_concept_relations` deliberately (EsoPolicyService.php:960-963):
 * `from` is the node BEING LEARNED, `to` is ITS PREREQUISITE. Read a row as
 * "from requires to".
 *
 * Deliberately NOT in App\Models\PAL\Models.php: that file holds 24 classes behind a
 * single PSR-4 entry and only resolves under an optimized classmap.
 */
class LearningRelation extends Model
{
    protected $table = 'pal_learning_relations';

    /** Node kinds this table may address. Concepts are excluded on purpose - see below. */
    public const NODE_TYPES = ['topic', 'unit', 'chapter'];

    protected $fillable = [
        'from_node_type', 'from_node_id', 'to_node_type', 'to_node_id',
        'relation_type', 'sub_institute_id', 'scope',
        'quality_status', 'tagged_by', 'confidence', 'note',
    ];

    protected $casts = [
        'from_node_id' => 'integer',
        'to_node_id' => 'integer',
        'sub_institute_id' => 'integer',
        'confidence' => 'float',
    ];

    /**
     * Tenant edges plus the shared ones.
     *
     * sub_institute_id 0 means "not owned by any one institute", so a tenant read
     * must include it or shared curriculum edges vanish. Mirrors
     * ConceptRelation::scopeForTenant so the two behave identically when merged.
     */
    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    /**
     * Whether this table is the right home for an edge between two node kinds.
     *
     * A concept -> concept edge belongs in `pal_concept_relations`; writing one here
     * would be invisible to the six services that read the concept graph (the ESO
     * prerequisite gate among them), silently splitting it in two. The write path
     * calls this and routes rather than guessing.
     */
    public static function handles(string $fromType, string $toType): bool
    {
        return in_array($fromType, self::NODE_TYPES, true)
            && in_array($toType, self::NODE_TYPES, true);
    }
}
