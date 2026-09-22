<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * One answered adaptive-practice question, for one learner on one concept.
 *
 * This table is both the learner's practice history AND the audit trail for
 * the adaptive engine: rule_fired records WHICH rule chose the difficulty that
 * was served, so a run of odd-looking questions can be explained after the
 * fact instead of guessed at.
 *
 * The difficulty and the rule are decided BEFORE the row is inserted, off the
 * history as it stood when the question was handed to the learner. That is why
 * they can be trusted as provenance: they are not re-derived later from a
 * history that now includes this very answer, and there is no hidden form
 * field for a replayed request to forge.
 *
 * difficulty_served / difficulty_source carry the same meaning as on
 * DiagnosticResponse - see that class.
 *
 * $timestamps is off: the live table has created_at only, and Eloquent would
 * otherwise try to write an updated_at column that does not exist.
 */
class AdaptiveResponse extends Model
{
    protected $table = 'pal_adaptive_response';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'concept_id', 'sub_institute_id', 'question_id', 'answer_master_id',
        'is_correct', 'difficulty_served', 'difficulty_source', 'concept_exact',
        'rule_fired', 'created_at',
    ];

    protected $casts = [
        'student_id'       => 'integer',
        'concept_id'       => 'integer',
        'sub_institute_id' => 'integer',
        'question_id'      => 'integer',
        'answer_master_id' => 'integer',
        'is_correct'       => 'boolean',
        'concept_exact'    => 'boolean',
        'created_at'       => 'datetime',
    ];

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', (int) $studentId);
    }

    public function scopeForConcept($query, $conceptId)
    {
        return $query->where('concept_id', (int) $conceptId);
    }
}
