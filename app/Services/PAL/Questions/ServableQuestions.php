<?php

namespace App\Services\PAL\Questions;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * What makes a question servable to a learner in PAL.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS REPLACED "question_type_id = 1"
 * ---------------------------------------------------------------------------
 * Every PAL surface used to hardcode `question_type_id = 1` ("multiple"). The
 * stated reason was sound - a learner must be able to click an answer, and
 * scoring free text server-side is out of scope - but the TEST was wrong. It
 * asked what a question is CALLED instead of whether it can actually be
 * answered and marked, and those are not the same question.
 *
 * Measured on tenants 1 and 341, 2026-09-16:
 *
 *   type 8  assertion & reason   129 questions - ALL have exactly 4 options and
 *                                exactly 1 correct answer
 *   type 7  CBE                   76 questions - same, 4 options, 1 correct
 *   type 3  ncert solution          5 questions - same
 *   type 2  narrative               4 questions that do have real options
 *   type 1  multiple            25839 questions - but min_opts is 1, so some
 *                                "MCQs" have a single option and nothing to choose
 *
 * So the old rule excluded 214 perfectly answerable questions for having the
 * wrong label, while admitting type-1 rows with one option that a learner
 * cannot meaningfully answer. Asking about options instead fixes both ends.
 *
 * ---------------------------------------------------------------------------
 * THE RULE
 * ---------------------------------------------------------------------------
 * A question is servable when `answer_master` holds at least MIN_OPTIONS rows
 * for it and at least one of them is flagged correct. That is exactly what the
 * learner UI and server-side marking each need, and nothing more.
 *
 * "At least one correct" rather than "exactly one" on purpose: every row
 * measured today has exactly one, but a multi-select item would still be
 * markable, and a rule that silently dropped it later would repeat the mistake
 * this class exists to undo.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS DELIBERATELY NOT
 * ---------------------------------------------------------------------------
 * It is not a claim that every servable question RENDERS identically. An
 * assertion & reason item has a different presentation from a plain MCQ, which
 * is why hydrate() returns `question_type_id` and `question_type` for the client
 * to lay out. It only decides answerability.
 *
 * It is also not a quality gate. `pal_question_metadata.quality_status` still
 * decides whether an item is approved (QuestionMetadata::scopeServable); this
 * class runs underneath that and answers a different question.
 */
final class ServableQuestions
{
    /**
     * Fewer than two options is not a choice. This is what excludes the
     * single-option type-1 rows the old filter let through.
     */
    public const MIN_OPTIONS = 2;

    /**
     * Narrow a query to questions a learner can actually answer.
     *
     * @param  QueryBuilder  $query
     * @param  string  $questionIdColumn  fully qualified, e.g. 'lqm.id'
     */
    public static function constrain($query, string $questionIdColumn)
    {
        return $query->whereExists(function ($sub) use ($questionIdColumn) {
            $sub->selectRaw('1')
                ->from('answer_master as pal_opt')
                ->whereColumn('pal_opt.question_id', $questionIdColumn)
                ->groupBy('pal_opt.question_id')
                ->havingRaw('COUNT(*) >= ?', [self::MIN_OPTIONS])
                ->havingRaw('SUM(pal_opt.correct_answer = 1) >= 1');
        });
    }

    /**
     * The question and its options.
     *
     * Returns null when the question cannot be answered, so callers get one
     * decision point rather than checking a type and then checking options.
     *
     * `correct_answer` now rides along on each option as `is_correct`. This
     * used to be stripped here on the reasoning that a pre-attempt GET must
     * never hand the answer key to the browser - but PAL Test and Practice
     * already return it the same way (unfiltered `answer_master` rows via
     * `answermasterModel`/`DB::table('answer_master')->get()`), so every
     * other PAL surface already accepts this exposure. Keeping this one path
     * answer-key-blind while the rest of the product isn't was an inconsistency,
     * not a security boundary - and it is what forced these four endpoints
     * onto plain radio buttons instead of the shared H5P-style QuestionPlayer,
     * which needs a resolvable correct answer to build a playable activity.
     * Server-side scoring (`isAnswerCorrect()`, `DiagnosticService::submit()`,
     * `AdaptiveLearningService::recordAnswer()`) is unchanged - it still
     * re-derives correctness from `answer_master` on submit, never trusting
     * whatever the client sends back.
     *
     * `standard_id`/`subject_id`/`chapter_id` ride along too, for the same
     * reason `is_correct` does: the shared H5P players (`SingleChoiceSetPlayer`
     * et al., reused unmodified from the authoring UI) refuse to render at all
     * without a non-empty curriculum context (`hasH5pContext()`), and these
     * three columns already exist on `lms_question_master` -- they were simply
     * never selected here, same as `correct_answer` above.
     *
     * @return array{question_id: int, title: string, question_type_id: int, question_type: ?string, standard_id: ?int, subject_id: ?int, chapter_id: ?int, options: array<int, array{id: int, answer: mixed, is_correct: bool}>}|null
     */
    public static function hydrate(int $questionId): ?array
    {
        $question = DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as t', 't.id', '=', 'q.question_type_id')
            ->where('q.id', $questionId)
            ->first([
                'q.id', 'q.question_title', 'q.question_type_id', DB::raw('t.question_type as question_type'),
                'q.standard_id', 'q.subject_id', 'q.chapter_id',
            ]);

        if ($question === null) {
            return null;
        }

        $rows = DB::table('answer_master')
            ->where('question_id', $questionId)
            ->get(['id', 'answer', 'correct_answer']);

        if ($rows->count() < self::MIN_OPTIONS) {
            return null;
        }

        if ($rows->filter(fn ($row) => (int) $row->correct_answer === 1)->isEmpty()) {
            // Answerable-looking but unmarkable: serving it would record every
            // attempt as wrong.
            return null;
        }

        $questionTypeId = (int) $question->question_type_id;

        return [
            'question_id' => (int) $question->id,
            'title' => $question->question_title,
            // Carried so the client can lay an assertion & reason item out
            // differently from a plain MCQ instead of flattening them.
            'question_type_id' => $questionTypeId,
            // Normalized to the grading engine's collapsed spelling for the
            // MCQ case, matching PalQuestionForms::describe()'s convention -
            // this is the value the frontend's mappingForQuestion() already
            // knows how to fall back on when no finer-grained type code is
            // available. Every other label is left exactly as it was.
            'question_type' => $questionTypeId === McqPool::MCQ_TYPE_ID ? 'MCQ' : ($question->question_type ?: null),
            'standard_id' => $question->standard_id === null ? null : (int) $question->standard_id,
            'subject_id' => $question->subject_id === null ? null : (int) $question->subject_id,
            'chapter_id' => $question->chapter_id === null ? null : (int) $question->chapter_id,
            'options' => $rows
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'answer' => $row->answer,
                    'is_correct' => (int) $row->correct_answer === 1,
                ])
                ->all(),
        ];
    }
}
