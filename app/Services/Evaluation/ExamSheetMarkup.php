<?php

namespace App\Services\Evaluation;

use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The marked-up copy of an answer sheet, drawn from the marks as they stand NOW.
 *
 * The uploaded scan is never touched: this only ever READS it and writes a separate file.
 * It used to be drawn once, by the evaluation job, from the AI's marks -- so a teacher who
 * changed a mark was looking at a tick or a cross the AI had put there. Now one place builds
 * the drawing from the stored answers, and three callers use it: the job (after a read), a
 * teacher's save (so reopening shows what they decided) and the live preview (unsaved edits
 * overlaid on the stored answers, drawn but not kept).
 *
 * What is drawn for each question, from the mark that counts (the teacher's if there is one,
 * else the AI's):
 *   - full marks      -> green tick
 *   - 0 marks         -> red cross
 *   - anything between-> amber triangle. Partial credit is neither a tick nor a cross, and a
 *                        teacher giving 3/5 is not marking the answer wrong.
 *   - not yet marked  -> a grey "?" and "awaiting teacher mark": an answer the AI could not
 *                        grade carries a 0 that is not a verdict, and a red cross there would
 *                        read as one.
 * beside it: the question as the SHEET numbers it, the mark as "3/5", and whose it is
 * ("AI" or "Teacher"), set in a different colour so the two read apart.
 *
 * Where it is drawn is where the reader found the answer (`page`, `box_2d`). A missing box is
 * not replaced by a guess: the existing annotator stacks it in the page margin, labelled with
 * its question number, which says which answer it is without claiming to know where it sits.
 */
class ExamSheetMarkup
{
    public function __construct(
        private readonly HomeworkAnnotatedPdfService $annotator,
        private readonly ExamEvaluationStorage $storage
    ) {
    }

    /**
     * What the question's mark looks like, from the mark that counts.
     *
     * @param  float|null  $teacherMarks  Null when no teacher has marked it.
     * @return array{status:string, awarded:?float, source:string}
     */
    public static function verdict(?float $aiMarks, ?float $teacherMarks, float $max, string $storedStatus, bool $needsAttention): array
    {
        if ($teacherMarks !== null) {
            return ['status' => self::statusFor($teacherMarks, $max), 'awarded' => $teacherMarks, 'source' => 'teacher'];
        }

        // An AI zero on an answer it could not grade is an absence of a mark, not a mark.
        if ($needsAttention && $storedStatus !== 'unattempted' && ($aiMarks === null || $aiMarks <= 0.0)) {
            return ['status' => 'pending', 'awarded' => null, 'source' => 'ai'];
        }

        if ($aiMarks === null) {
            return ['status' => 'pending', 'awarded' => null, 'source' => 'ai'];
        }

        return ['status' => self::statusFor($aiMarks, $max), 'awarded' => $aiMarks, 'source' => 'ai'];
    }

    public static function statusFor(float $marks, float $max): string
    {
        if ($max > 0.0 && $marks >= $max) {
            return 'correct';
        }

        return $marks > 0.0 ? 'partially_correct' : 'wrong';
    }

    /**
     * @param  iterable<object>  $answers   Rows of exam_evaluation_answer.
     * @param  array<int,float|null>  $teacherOverrides  question_no => an unsaved teacher mark (null = cleared).
     * @return array<int,array<string,mixed>>
     */
    public function annotationsFor(iterable $answers, array $teacherOverrides = []): array
    {
        $annotations = [];
        $answers = collect($answers);
        $inferred = $answers->contains(fn ($row) => ! empty($row->question_label))
            ? []
            : QuestionLabel::inferFromLayout($answers->mapWithKeys(fn ($row) => [(int) $row->question_no => (bool) $row->is_objective])->all());

        foreach ($answers as $answer) {
            $questionNo = (int) $answer->question_no;
            $max = (float) $answer->max_marks;
            $teacher = array_key_exists($questionNo, $teacherOverrides)
                ? $teacherOverrides[$questionNo]
                : ($answer->teacher_marks === null ? null : (float) $answer->teacher_marks);
            $ai = $answer->ai_marks === null ? null : (float) $answer->ai_marks;
            $needsAttention = ! $answer->is_objective
                && $answer->status !== 'unattempted'
                && (float) $answer->ai_confidence < AnswerSheetScoringService::CONFIDENCE_REVIEW_THRESHOLD;

            $verdict = self::verdict($ai, $teacher, $max, (string) $answer->status, $needsAttention);

            // Nothing was written and nobody has marked it: a cross beside blank space reads
            // as "wrong" rather than "nothing here". A teacher's own mark on it is still shown.
            if ($answer->status === 'unattempted' && ($verdict['source'] === 'ai' || ($verdict['awarded'] ?? 0.0) <= 0.0)) {
                continue;
            }

            $pending = $verdict['status'] === 'pending';
            $label = QuestionLabel::display(($answer->question_label ?? null) ?: ($inferred[$questionNo] ?? null), $questionNo);

            $annotations[] = [
                'question_no' => $questionNo,
                'status' => $verdict['status'],
                'expected_answer' => (string) $answer->expected_answer,
                'remarks' => $verdict['source'] === 'teacher' ? '' : (string) $answer->ai_remark,
                'page' => max(1, (int) $answer->page),
                'box_2d' => $this->box($answer->box_2d),
                'label' => $label,
                'score_text' => $pending ? 'awaiting teacher mark' : $this->trim($verdict['awarded']) . '/' . $this->trim($max),
                'source' => $pending ? 'ai' : $verdict['source'],
                'source_tag' => $pending ? '' : ($verdict['source'] === 'teacher' ? '(Teacher)' : '(AI)'),
            ];
        }

        return $annotations;
    }

    /**
     * The marked-up PDF as bytes, or null when there is nothing to draw or the scan is gone.
     * Nothing is stored.
     *
     * @param  iterable<object>  $answers
     * @param  array<int,float|null>  $teacherOverrides
     */
    public function render(object $sheet, iterable $answers, array $teacherOverrides = []): ?string
    {
        $path = $this->storage->sheetPath((string) $sheet->file_name);

        if ($path === null) {
            return null;
        }

        $annotations = $this->annotationsFor($answers, $teacherOverrides);

        if ($annotations === []) {
            return null;
        }

        return $this->annotator->annotate($path, (string) ($sheet->mime_type ?: 'application/pdf'), $annotations);
    }

    /**
     * Redraws the sheet's saved marked-up copy from its stored marks and points the sheet at it.
     *
     * Read, drawn and recorded under the sheet's row lock, so two saves close together cannot
     * leave the older drawing as the final one. Never throws: a marked-up copy is a convenience,
     * and losing it must not lose, or fail, a teacher's save.
     *
     * @return string|null  The new file name, or null when none was produced.
     */
    public function regenerate(int $sheetId): ?string
    {
        try {
            $oldName = null;
            $newName = null;

            DB::transaction(function () use ($sheetId, &$oldName, &$newName) {
                $sheet = DB::table('exam_evaluation_sheet')->where('id', $sheetId)->lockForUpdate()->first();

                if (! $sheet) {
                    return;
                }

                $oldName = $sheet->annotated_file_name ?: null;
                $answers = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->orderBy('question_no')->get();

                if ($this->storage->sheetPath((string) $sheet->file_name) === null) {
                    return; // the scan is gone: keep whatever copy there is
                }

                $pdf = $this->render($sheet, $answers);
                $newName = $pdf === null ? null : $this->storage->putAnnotated($sheetId, $pdf);

                DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['annotated_file_name' => $newName]);
            });

            if ($oldName !== null && $oldName !== $newName) {
                $this->storage->delete(null, $oldName);
            }

            return $newName;
        } catch (Throwable $exception) {
            Log::warning('Marked-up answer sheet could not be redrawn', ['sheet_id' => $sheetId, 'message' => $exception->getMessage()]);

            return null;
        }
    }

    /** @return array{0:int,1:int,2:int,3:int}|null */
    private function box(mixed $stored): ?array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (! is_array($stored) || count($stored) !== 4) {
            return null;
        }

        $values = array_map(static fn ($value) => (int) $value, array_values($stored));

        foreach ($values as $value) {
            if ($value < 0 || $value > 1000) {
                return null;
            }
        }

        return ($values[0] < $values[2] && $values[1] < $values[3]) ? $values : null;
    }

    private function trim(?float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
