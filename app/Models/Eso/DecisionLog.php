<?php

namespace App\Models\Eso;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptive Learning Engine — eso_decision_log.
 *
 * Append-only audit trail for every D1-D5 decision. Never updated after
 * creation; write once per decision via EsoPolicyService::log().
 */
class DecisionLog extends Model
{
    protected $table = 'eso_decision_log';

    protected $fillable = [
        'student_id', 'concept_id', 'node_id', 'sub_institute_id',
        'state_snapshot', 'rule_fired', 'action', 'llm_instruction',
    ];

    protected $casts = [
        'state_snapshot' => 'array',
    ];

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForConcept($query, int $conceptId)
    {
        return $query->where('concept_id', $conceptId);
    }
}
