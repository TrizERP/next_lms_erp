<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

/**
 * One served question inside a diagnostic attempt, and the learner's answer.
 *
 * Created unanswered when the paper is drawn; updated in place on submit.
 * `answer_master_id IS NULL` is the ONE test for "unanswered" everywhere in
 * this feature - is_correct is a plain tinyint and says nothing about whether
 * the learner actually responded.
 *
 * ---------------------------------------------------------------------------
 * difficulty_served vs difficulty_source
 * ---------------------------------------------------------------------------
 * difficulty_served is the SLOT the item filled: what the learner saw on the
 * badge, and what the per-band scoring counts. It is always easy|medium|hard.
 *
 * difficulty_source is where that claim came from:
 *   'dok'              a Depth-of-Knowledge tag said so (the strong signal)
 *   'g_difficulty'     no DoK tag existed; the generated column said so
 *   'adjacent:medium'  the bank could not fill this band, so a neighbouring
 *                      item was borrowed into the slot
 *   'untagged'         nothing said anything; the item filled a gap
 *
 * Keeping them apart is the point. A borrowed item is still counted in the
 * band it filled - otherwise the buckets would not sum to total_questions -
 * but the row records honestly that the bank never proved the difficulty.
 *
 * concept_exact records which join found the concept: 1 when the question
 * carried a usable concept_id of its own, 0 when it was resolved through its
 * chapter. concept_id is populated on only ~2k of 28k servable MCQs on this
 * estate, so the chapter path is the normal case, not the exception.
 */
class DiagnosticResponse extends Model
{
    protected $table = 'pal_diagnostic_response';

    public $timestamps = false;

    protected $fillable = [
        'attempt_id', 'question_id', 'answer_master_id', 'is_correct',
        'difficulty_served', 'difficulty_source',
        'concept_id_snapshot', 'chapter_id_snapshot', 'concept_exact',
        'sequence', 'answered_at',
    ];

    protected $casts = [
        'attempt_id'          => 'integer',
        'question_id'         => 'integer',
        'answer_master_id'    => 'integer',
        'is_correct'          => 'boolean',
        'concept_id_snapshot' => 'integer',
        'chapter_id_snapshot' => 'integer',
        'concept_exact'       => 'boolean',
        'sequence'            => 'integer',
        'answered_at'         => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(DiagnosticAttempt::class, 'attempt_id');
    }

    /** The learner chose an option. Distinct from having chosen correctly. */
    public function wasAnswered(): bool
    {
        return $this->answer_master_id !== null;
    }
}
