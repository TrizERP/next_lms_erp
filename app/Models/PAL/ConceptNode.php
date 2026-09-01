<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Adaptive Learning Engine — K/A/S (Knowledge/Ability/Skill) node identity.
 *
 * One row per masterable sub-unit of a concept. `learner_node_state.node_id`
 * and `eso_decision_log.node_id` both reference this table's id — see
 * docs/ADAPTIVE_LEARNING_ENGINE_IMPLEMENTATION_PLAN.md §L.2/§D for why this
 * exists as its own table rather than fields on ConceptMetadata.
 */
class ConceptNode extends Model
{
    protected $table = 'pal_concept_nodes';

    protected $fillable = [
        'concept_id', 'sub_institute_id', 'node_type', 'label', 'description',
        'mastery_threshold', 'sort_order',
    ];

    protected $casts = [
        'mastery_threshold' => 'float',
        'sort_order' => 'integer',
    ];

    /**
     * The real concept row. `concept_id` points at `lms_concept` (the live
     * concept catalogue this feature builds on — see the implementation
     * plan §A), not `App\Models\PAL\Concept` (`pal_concepts`, the unrelated,
     * unfed PAL V4 table of the same name) — no Eloquent model exists for
     * `lms_concept` anywhere in this codebase, so this reads it the same way
     * QuestionMetadata::questionRow() reads lms_question_master.
     */
    public function conceptRow(): ?object
    {
        return DB::table('lms_concept')->where('id', $this->concept_id)->first();
    }

    public function conceptMetadata()
    {
        return $this->belongsTo(ConceptMetadata::class, 'concept_id', 'concept_ref_id');
    }

    public function scopeForConcept($query, int $conceptId)
    {
        return $query->where('concept_id', $conceptId);
    }

    public function scopeForTenant($query, ?int $subInstituteId)
    {
        if ($subInstituteId === null) {
            return $query;
        }

        return $query->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]));
    }

    /**
     * The threshold to grade this node's mastery against: the node's own
     * override if set, else the concept's mastery_gate, else the brief's
     * D1/D2 default (0.8 skip-eligible / 0.75 prerequisite-clear — callers
     * pick which default applies, this just resolves the node-level value).
     */
    public function effectiveThreshold(?float $conceptMasteryGate, float $fallback): float
    {
        return $this->mastery_threshold ?? $conceptMasteryGate ?? $fallback;
    }
}
