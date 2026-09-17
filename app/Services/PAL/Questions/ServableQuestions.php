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
     * The question and its options, WITHOUT the answer key.
     *
     * Returns null when the question cannot be answered, so callers get one
     * decision point rather than checking a type and then checking options.
     *
     * `correct_answer` is read here only to decide servability and is never
     * placed in the returned array - correctness is resolved server-side from
     * the chosen answer_master id. Adding it to the payload would hand the
     * answer to the browser.
     *
     * @return array{question_id: int, title: string, question_type_id: int, question_type: ?string, options: array<int, array{id: int, answer: mixed}>}|null
     */
    public static function hydrate(int $questionId): ?array
    {
        $question = DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as t', 't.id', '=', 'q.question_type_id')
            ->where('q.id', $questionId)
            ->first(['q.id', 'q.question_title', 'q.question_type_id', DB::raw('t.question_type as question_type')]);

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

        return [
            'question_id' => (int) $question->id,
            'title' => $question->question_title,
            // Carried so the client can lay an assertion & reason item out
            // differently from a plain MCQ instead of flattening them.
            'question_type_id' => (int) $question->question_type_id,
            'question_type' => $question->question_type ?: null,
            'options' => $rows
                ->map(fn ($row) => ['id' => (int) $row->id, 'answer' => $row->answer])
                ->all(),
        ];
    }
}
