<?php

namespace App\Jobs;

use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Evaluates one scanned answer sheet against its paper's marking key.
 *
 * Read -> identify -> score -> annotate -> save, and then the sheet waits.
 * What this job produces is a PROPOSAL: `ai_marks` on every answer and
 * `ai_total` on the sheet, with the sheet left in `Evaluated` or
 * `Needs review`. It never writes `teacher_marks`, never sets `Approved`, and
 * nothing it writes reaches the gradebook -- publishing reads `teacher_marks`
 * and only a teacher puts a number there.
 *
 * Marking up the student's own scanned pages (rather than producing a separate
 * typed report) reuses HomeworkAnnotatedPdfService, which already draws a
 * tick, cross or triangle at a box_2d on the original page -- the same thing a
 * teacher does with a red pen, which is what makes the result recognisable to
 * a parent looking at it.
 *
 * On QUEUE_CONNECTION=sync this runs inline in the upload request, which is
 * fine for one sheet and much too slow for a class set. Point QUEUE_CONNECTION
 * at database/redis and run a worker and a batch processes in the background
 * with no code change here.
 */
class EvaluateAnswerSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(private readonly int $sheetId)
    {
    }

    public function handle(
        AnswerKeyService $answerKeys,
        AnswerSheetReaderService $reader,
        AnswerSheetScoringService $scorer,
        StudentMatcherService $matcher,
        HomeworkAnnotatedPdfService $annotator,
        ExamEvaluationStorage $storage
    ): void {
        $sheet = DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->first();

        if (! $sheet) {
            return;
        }

        $batch = DB::table('exam_evaluation_batch')->where('id', $sheet->batch_id)->first();

        if (! $batch) {
            $this->markFailed('The batch this sheet belongs to no longer exists.');

            return;
        }

        $absolutePath = $storage->sheetPath((string) $sheet->file_name);

        if ($absolutePath === null) {
            $this->markFailed('The uploaded file could not be found on the server.');

            return;
        }

        DB::table('exam_evaluation_sheet')
            ->where('id', $this->sheetId)
            ->update(['status' => 'Processing', 'failure_reason' => null, 'updated_at' => now()]);

        try {
            $key = $answerKeys->forPaper(
                (int) $batch->question_paper_id,
                [(int) $batch->sub_institute_id]
            );
        } catch (Throwable $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        $mimeType = (string) ($sheet->mime_type ?: 'application/pdf');

        try {
            $read = $reader->read(
                $absolutePath,
                $mimeType,
                $answerKeys->readerView($key['questions']),
                $key['paper']
            );
        } catch (DocumentExtractionException $exception) {
            Log::warning('Answer sheet could not be read', [
                'sheet_id' => $this->sheetId,
                'message' => $exception->getMessage(),
            ]);
            $this->markFailed($exception->getMessage());

            return;
        }

        $scored = $scorer->score($key['questions'], $read['responses']);

        $identity = $matcher->match(
            $read['student'],
            $matcher->roster(
                (int) $batch->sub_institute_id,
                (int) $batch->syear,
                (int) $key['paper']['standard_id'],
                (int) $key['paper']['grade_id']
            )
        );

        $annotatedName = $this->annotate($annotator, $storage, $absolutePath, $mimeType, $scored['answers']);

        $this->persist($sheet, $read, $scored, $identity, $annotatedName);
        $this->refreshBatchCounters((int) $sheet->batch_id);
    }

    /**
     * @param  array<int,array<string,mixed>>  $answers
     */
    private function annotate(
        HomeworkAnnotatedPdfService $annotator,
        ExamEvaluationStorage $storage,
        string $absolutePath,
        string $mimeType,
        array $answers
    ): ?string {
        $annotations = [];

        foreach ($answers as $answer) {
            // An untouched question gets no mark drawn on it: a cross next to a
            // blank space reads as "this was wrong" rather than "nothing was
            // written here".
            if ($answer['status'] === 'unattempted') {
                continue;
            }

            $annotations[] = [
                'question_no' => (int) $answer['question_no'],
                'status' => (string) $answer['status'],
                'expected_answer' => (string) $answer['expected_answer'],
                'remarks' => sprintf(
                    '%s/%s  %s',
                    $this->trimNumber((float) $answer['ai_marks']),
                    $this->trimNumber((float) $answer['max_marks']),
                    (string) $answer['ai_remark']
                ),
                'page' => (int) $answer['page'],
                'box_2d' => $answer['box_2d'] ?? null,
            ];
        }

        if ($annotations === []) {
            return null;
        }

        try {
            return $storage->putAnnotated(
                $this->sheetId,
                $annotator->annotate($absolutePath, $mimeType, $annotations)
            );
        } catch (Throwable $exception) {
            // Non-fatal on purpose: the marks are the result, the marked-up
            // copy is a convenience. Losing the PDF must not lose the grading.
            Log::warning('Annotated answer sheet could not be produced', [
                'sheet_id' => $this->sheetId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string,mixed>  $read
     * @param  array<string,mixed>  $scored
     * @param  array<string,mixed>  $identity
     */
    private function persist(object $sheet, array $read, array $scored, array $identity, ?string $annotatedName): void
    {
        $now = now();
        $maxMarks = (float) $scored['max_marks'];
        $aiTotal = (float) $scored['ai_total'];

        DB::transaction(function () use ($sheet, $read, $scored, $identity, $annotatedName, $now, $maxMarks, $aiTotal) {
            DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->delete();

            $rows = [];

            foreach ($scored['answers'] as $answer) {
                $rows[] = [
                    'sheet_id' => $this->sheetId,
                    'batch_id' => (int) $sheet->batch_id,
                    'question_id' => (int) $answer['question_id'] ?: null,
                    'question_no' => (int) $answer['question_no'],
                    'question_type' => mb_substr((string) $answer['question_type'], 0, 60),
                    'is_objective' => (bool) $answer['is_objective'],
                    'detected_answer' => (string) $answer['detected_answer'],
                    'selected_options' => mb_substr(implode(',', (array) $answer['selected_options']), 0, 100),
                    'expected_answer' => (string) $answer['expected_answer'],
                    'max_marks' => (float) $answer['max_marks'],
                    'ai_marks' => (float) $answer['ai_marks'],
                    // Deliberately null. An objective mark is deterministic and
                    // a teacher will almost always accept it, but accepting is
                    // still their act -- approval copies ai_marks across.
                    'teacher_marks' => null,
                    'status' => (string) $answer['status'],
                    'ai_confidence' => (float) $answer['ai_confidence'],
                    'ai_remark' => mb_substr((string) $answer['ai_remark'], 0, 500),
                    'page' => (int) $answer['page'],
                    'box_2d' => $answer['box_2d'] ? json_encode($answer['box_2d']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('exam_evaluation_answer')->insert($chunk);
            }

            DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->update([
                'detected_roll_no' => mb_substr((string) $read['student']['roll_no'], 0, 50),
                'detected_enrollment_no' => mb_substr((string) $read['student']['enrollment_no'], 0, 50),
                'detected_student_name' => mb_substr((string) $read['student']['name'], 0, 150),
                'student_id' => $identity['student_id'],
                'identity_confidence' => $identity['identity_confidence'],
                'identity_source' => $identity['identity_source'],
                // An unmatched sheet needs a teacher whatever the marks look
                // like -- marks with nobody to put them against are not a result.
                'status' => ($scored['needs_review'] || $identity['student_id'] === null)
                    ? 'Needs review'
                    : 'Evaluated',
                'ai_total' => $aiTotal,
                'max_marks' => $maxMarks,
                'percentage' => $maxMarks > 0 ? round($aiTotal / $maxMarks * 100, 2) : null,
                'annotated_file_name' => $annotatedName,
                'ai_result_json' => json_encode([
                    'student' => $read['student'],
                    'ai_total' => $aiTotal,
                    'max_marks' => $maxMarks,
                ]),
                'failure_reason' => null,
                'evaluated_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Recomputes the batch's counters from its sheets.
     *
     * Counted rather than incremented: sheets get re-run, deleted and
     * re-uploaded, and a counter that is only ever nudged up drifts away from
     * the truth the first time one of those happens.
     */
    public static function refreshBatch(int $batchId): void
    {
        $counts = DB::table('exam_evaluation_sheet')
            ->where('batch_id', $batchId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('Evaluated','Needs review','Approved') THEN 1 ELSE 0 END) as evaluated")
            ->selectRaw("SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved")
            // A failed sheet is finished with, even though it produced no
            // marks. Counting it as still in flight would leave the batch
            // showing "Processing" forever over a scan that will never read.
            ->selectRaw("SUM(CASE WHEN status IN ('Evaluated','Needs review','Approved','Failed') THEN 1 ELSE 0 END) as settled")
            ->first();

        $total = (int) ($counts->total ?? 0);
        $evaluated = (int) ($counts->evaluated ?? 0);
        $approved = (int) ($counts->approved ?? 0);
        $settled = (int) ($counts->settled ?? 0);

        $batch = DB::table('exam_evaluation_batch')->where('id', $batchId)->first();

        if (! $batch) {
            return;
        }

        // Published is terminal -- a batch that has already reached the
        // gradebook does not slide back to Review because a sheet was re-run.
        $status = (string) $batch->status;

        if ($status !== 'Published') {
            if ($total === 0) {
                $status = 'Draft';
            } elseif ($settled < $total) {
                $status = 'Processing';
            } else {
                $status = 'Review';
            }
        }

        DB::table('exam_evaluation_batch')->where('id', $batchId)->update([
            'total_sheets' => $total,
            'evaluated_sheets' => $evaluated,
            'approved_sheets' => $approved,
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function refreshBatchCounters(int $batchId): void
    {
        self::refreshBatch($batchId);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('EvaluateAnswerSheetJob failed permanently', [
            'sheet_id' => $this->sheetId,
            'message' => $exception->getMessage(),
        ]);

        $this->markFailed($exception->getMessage());
    }

    private function markFailed(string $reason): void
    {
        $batchId = (int) DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->value('batch_id');

        DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->update([
            'status' => 'Failed',
            'failure_reason' => mb_substr($reason, 0, 250),
            'evaluated_at' => now(),
            'updated_at' => now(),
        ]);

        if ($batchId > 0) {
            self::refreshBatch($batchId);
        }
    }

    /** 2.00 -> "2", 1.50 -> "1.5" -- marks read badly with trailing zeros. */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
