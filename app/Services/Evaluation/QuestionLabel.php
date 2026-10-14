<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Facades\Schema;

/**
 * The question number PRINTED on an answer sheet, as opposed to the question's place in the paper.
 *
 * Two different numbers have always been in play and were being confused:
 *
 *  - `question_no` on `exam_evaluation_answer` is the question's POSITION in
 *    `question_paper.question_ids` (1..N). It is how a sheet's answer is tied to the marking
 *    key and to `question_id`; it is never shown to a teacher as "the number".
 *  - the PRINTED number is whatever the exam sheet itself says: Q.1(A), Q.1(B), ... Q.2, Q.3.
 *    Nothing in the paper tables stores it (a paper is just a list of question ids and the
 *    app's own printout simply counts 1..N), so it can only come from the sheet. The reader
 *    reports it per answer and it lands in `question_label`.
 *
 * A label is shown only when the sheet actually gave one that can be trusted. Without one the
 * answer is shown by its paper position and says so, so a position is never mistaken for the
 * number printed on the paper.
 */
final class QuestionLabel
{
    private static ?bool $column = null;

    /** Longest label stored (the column is 40; real ones are a handful of characters). */
    private const MAX_LENGTH = 24;

    /**
     * "q.1 (a)", "Question 2", "1-A", "Q1(A)(ii)" -> "Q.1(A)", "Q.2", "Q.1(A)", "Q.1(A)(ii)".
     *
     * Null for anything that does not look like a question number: an empty string, prose, or a
     * label that merely echoes the paper position cannot be told from a real one here, which is
     * why duplicates are dealt with separately by withoutDuplicates().
     */
    public static function normalise(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_int($raw)) {
            return null;
        }

        $text = trim((string) $raw);
        $text = preg_replace('/^(question|ques|qn|q)\b\.?\s*(no\.?|number)?\s*[:.\-]?\s*/i', '', $text) ?? $text;
        $text = trim($text, " \t\n\r.:");

        $pattern = '/^(\d{1,3})\s*[.\-]?\s*(?:\(?\s*([A-Za-z]|[ivxIVX]{2,4})\s*\)?)?\s*(?:\(\s*([ivxIVX]{1,4}|[a-z])\s*\))?$/';

        if ($text === '' || ! preg_match($pattern, $text, $m)) {
            return null;
        }

        $label = 'Q.' . (int) $m[1];

        // First sub-part: a letter (A) or a roman numeral (ii); second: lower case, as printed papers do.
        if (($m[2] ?? '') !== '') {
            $label .= '(' . (strlen($m[2]) > 1 ? strtolower($m[2]) : strtoupper($m[2])) . ')';
        }

        if (($m[3] ?? '') !== '') {
            $label .= '(' . strtolower($m[3]) . ')';
        }

        return mb_strlen($label) <= self::MAX_LENGTH ? $label : null;
    }

    /**
     * Drops every label that appears more than once on a sheet.
     *
     * Two answers cannot both be Q.2. When a reader repeats a label it has confused two of
     * them, and showing either would be a guess -- so both fall back to "not read".
     *
     * @param  array<int,string|null>  $labelsByQuestionNo
     * @return array<int,string|null>
     */
    public static function withoutDuplicates(array $labelsByQuestionNo): array
    {
        $counts = array_count_values(array_filter($labelsByQuestionNo, static fn ($label) => $label !== null && $label !== ''));

        return array_map(
            static fn ($label) => ($label !== null && ($counts[$label] ?? 0) === 1) ? $label : null,
            $labelsByQuestionNo
        );
    }

    /**
     * A numbering worked out from the paper's LAYOUT, for sheets whose printed numbers were not read.
     *
     * Printed papers group a run of objective questions under one number -- Q.1(A), Q.1(B) ... --
     * and give each written question its own: Q.2, Q.3 ... So a run of two or more consecutive
     * objective questions becomes Q.n(A), Q.n(B)...; any other question takes the next number.
     * A convention, not a reading: it is only a stand-in for what the sheet printed, is shown as
     * inferred, and is never mixed with labels that WERE read (see usable()).
     *
     * @param  array<int,bool>  $objectiveByPosition  Paper position => is objective, in paper order.
     * @return array<int,string>  Position => label.
     */
    public static function inferFromLayout(array $objectiveByPosition): array
    {
        ksort($objectiveByPosition);

        $positions = array_keys($objectiveByPosition);
        $labels = [];
        $number = 0;
        $i = 0;

        while ($i < count($positions)) {
            $run = 1;

            if ($objectiveByPosition[$positions[$i]]) {
                while ($i + $run < count($positions) && $objectiveByPosition[$positions[$i + $run]]) {
                    $run++;
                }
            }

            $number++;

            for ($k = 0; $k < $run; $k++) {
                $labels[$positions[$i + $k]] = $run > 1 ? 'Q.' . $number . '(' . chr(65 + $k % 26) . ')' : 'Q.' . $number;
            }

            $i += $run;
        }

        return $labels;
    }

    /** What the screen and the marked-up PDF call a question. */
    public static function display(?string $label, int $position): string
    {
        return ($label !== null && $label !== '') ? $label : 'Q' . $position;
    }

    /** `question_label` arrives with a migration; until then everything works on positions alone. */
    public static function columnExists(): bool
    {
        return self::$column ??= Schema::hasColumn('exam_evaluation_answer', 'question_label');
    }

    /** For tests that rebuild the schema between cases. */
    public static function forgetSchema(): void
    {
        self::$column = null;
    }
}
