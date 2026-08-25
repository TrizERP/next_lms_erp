<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * BKT mastery state for one (learner, lms_concept) pair — the learner overlay
 * the Set Coherence Map is read through.
 *
 * `p_mastery` is owned by App\Services\PAL\Runtime\BktEngine. Nothing else may
 * write it: the recommender's prerequisite gate is only meaningful if every
 * value on the axis was produced by the same estimator.
 */
class ConceptMastery extends Model
{
    protected $table = 'pal_concept_mastery';

    protected $fillable = [
        'learner_id', 'concept_ref_id', 'sub_institute_id',
        'p_mastery', 'band', 'attempts', 'correct', 'streak', 'mastery_gate',
        'first_evidence_at', 'last_evidence_at', 'graph_synced_at',
    ];

    protected $casts = [
        'p_mastery'         => 'float',
        'mastery_gate'      => 'float',
        'attempts'          => 'integer',
        'correct'           => 'integer',
        'streak'            => 'integer',
        'first_evidence_at' => 'datetime',
        'last_evidence_at'  => 'datetime',
        'graph_synced_at'   => 'datetime',
    ];

    /** Rows owed to Neo4j: never synced, or changed since the last sync. */
    public function scopeDirty($query)
    {
        return $query->whereNull('graph_synced_at')
            ->orWhereColumn('graph_synced_at', '<', 'updated_at');
    }

    public function scopeForLearner($query, int $learnerId)
    {
        return $query->where('learner_id', $learnerId);
    }

    /** True when this concept counts as mastered for gating purposes. */
    public function isMastered(): bool
    {
        return $this->p_mastery >= $this->mastery_gate;
    }
}
