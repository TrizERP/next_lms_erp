<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * One sitting of the PAL chapter diagnostic - 15 MCQs, 5 per difficulty band.
 *
 * The attempt row and all of its response rows are written when the paper is
 * STARTED, not when it is submitted. Three things follow from that and the
 * rest of the flow depends on all three:
 *
 *   1. An unanswered question is already a row, so nothing has to be
 *      reconstructed at submit time to count it.
 *   2. Submit only ever UPDATES pre-existing rows, so a browser cannot score
 *      a question it was never served.
 *   3. The paper survives a refresh - the learner gets their own paper back,
 *      not a fresh draw.
 *
 * `percentage` is always computed against total_questions (the number actually
 * served), never against a hardcoded 15, because a thin chapter can legally
 * yield fewer.
 *
 * $timestamps is off: this table carries started_at / submitted_at, not the
 * created_at / updated_at pair Eloquent would otherwise insist on.
 */
class DiagnosticAttempt extends Model
{
    protected $table = 'pal_diagnostic_attempt';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'subject_id', 'chapter_id', 'standard_id', 'sub_institute_id', 'syear',
        'status', 'total_questions', 'correct', 'incorrect', 'unanswered', 'percentage', 'level',
        'difficulty_breakdown', 'concept_breakdown', 'selection_report',
        'started_at', 'submitted_at',
    ];

    protected $casts = [
        'student_id'       => 'integer',
        'subject_id'       => 'integer',
        'chapter_id'       => 'integer',
        'standard_id'      => 'integer',
        'sub_institute_id' => 'integer',
        'syear'            => 'integer',
        'total_questions'  => 'integer',
        'correct'          => 'integer',
        'incorrect'        => 'integer',
        'unanswered'       => 'integer',
        'percentage'       => 'float',
        // The live columns are longtext, not json. The cast is what makes them
        // behave as arrays; a json() column type would not match production.
        'difficulty_breakdown' => 'array',
        'concept_breakdown'    => 'array',
        'selection_report'     => 'array',
        'started_at'           => 'datetime',
        'submitted_at'         => 'datetime',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';

    /** Ordered by the sequence the learner was shown, not by insert id. */
    public function responses()
    {
        return $this->hasMany(DiagnosticResponse::class, 'attempt_id')->orderBy('sequence');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', (int) $studentId);
    }

    public function scopeForChapter($query, $chapterId)
    {
        return $query->where('chapter_id', (int) $chapterId);
    }

    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('subject_id', (int) $subjectId);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
