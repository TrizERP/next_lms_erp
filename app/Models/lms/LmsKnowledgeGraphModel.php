<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

/**
 * One "concept requires prerequisite_concept" edge. Populated heuristically
 * by PedagogyEngineController::buildKnowledgeGraph() from question
 * pre_grade_topic tagging — not yet curriculum-authored — but read directly
 * by the prerequisite gate (getConceptGateStatus) and the diagnostic's
 * prerequisite-gap detection (classifyDiagnosticResults).
 */
class LmsKnowledgeGraphModel extends Model
{
    protected $table = 'lms_knowledge_graph';

    protected $fillable = [
        'concept_id',
        'prerequisite_concept_id',
        'relationship_type',
        'strength',
    ];

    protected $casts = [
        'strength' => 'integer',
    ];

    public function concept()
    {
        return $this->belongsTo(LmsConceptModel::class, 'concept_id');
    }

    public function prerequisiteConcept()
    {
        return $this->belongsTo(LmsConceptModel::class, 'prerequisite_concept_id');
    }
}
