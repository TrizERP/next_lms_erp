<?php

namespace App\Jobs;

use App\Services\Evaluation\AnswerKeyService;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\ExamSheetMarkup;
use App\Services\Evaluation\QuestionLabel;
use App\Services\Evaluation\QueueTiming;
use App\Services\Evaluation\SheetIdentity;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\HomeworkAnnotatedPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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

    /**
     * Four goes: a busy provider or a per-minute rate limit is waited out
     * (see backoff()), and every other failure is recorded on the first pass and
     * not repeated.
     */
    public int $tries = 4;

    public int $timeout = 600;

    public function __construct(private readonly int $sheetId)
    {
    }

    /** @return array<int,int> Seconds before each retry; a rate limit needs minutes, not seconds. */
    public function backoff(): array
    {
        return [60, 180, 420];
    }

    /**
     * One run per sheet at a time. A second dispatch for a sheet that is already
     * being read (a double click on "Re-run", a worker that picked up a
     * redelivered job) is dropped, not queued behind it: the first run will leave
     * the sheet in its final state and a second would only repeat the model call.
     *
     * @return array<int,object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->sheetId))->dontRelease()->expireAfter($this->timeout + 60)];
    }

    public function handle(
        AnswerKeyService $answerKeys,
        AnswerSheetReaderService $reader,
        AnswerSheetScoringService $scorer,
        StudentMatcherService $matcher,
        HomeworkAnnotatedPdfService $annotator,
        ExamEvaluationStorage $storage
    ): void {
        // A lease shorter than this job's timeout means a slow run is handed to a second
        // worker. Says so in the log once; changes nothing.
        QueueTiming::warnIfLeaseIsShorterThanTimeout(self::class, $this->timeout, $this->job?->getConnectionName() ?? $this->connection);

        $sheet = DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->first();

        if (! $sheet) {
            return;
        }

        // Only a sheet that is waiting (or was mid-run when a worker died) is
        // evaluated. Anything already finished -- above all one a teacher has
        // approved -- is left alone, so a stale or duplicate job can never
        // overwrite reviewed work. Re-running a finished sheet goes through
        // reprocess(), which sets it back to Pending first.
        if (! in_array($sheet->status, ['Pending', 'Processing'], true)) {
            Log::info('Answer sheet evaluation skipped; sheet is not waiting', [
                'sheet_id' => $this->sheetId,
                'status' => $sheet->status,
            ]);

            return;
        }

        $batch = DB::table('exam_evaluation_batch')->where('id', $sheet->batch_id)->first();

        if (! $batch) {
            $this->markFailed('The batch this sheet belongs to no longer exists.');

            return;
        }

        // Provider, model and key come from the batch school's saved Homework & Assignment Evaluation configuration.
        $reader = $reader->forInstitute((int) $batch->sub_institute_id);
        $scorer = $scorer->forInstitute((int) $batch->sub_institute_id);

        $absolutePath = $storage->sheetPath((string) $sheet->file_name);

        if ($absolutePath === null) {
            $this->markFailed(SheetIdentity::MISSING_FILE_MESSAGE);

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
        } catch (ProviderBusyException|RateLimitedException|ConnectionException $exception) {
            // Not the sheet's fault: the provider could not answer just now.
            // Raised to the queue, which retries after backoff(); if every try
            // is spent failed() records it on the sheet.
            Log::info('Answer sheet reading deferred; provider unavailable', [
                'sheet_id' => $this->sheetId,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // On every try but the last, a provider hiccup during the written-answer
        // pass is raised so the whole sheet is retried. On the last try it
        // degrades instead: objective marks stand and the written answers go to
        // the teacher flagged, which beats failing a fully read sheet.
        $scored = $scorer->score($key['questions'], $read['responses'], $this->attempts() < $this->tries);

        $identity = $matcher->match(
            $read['student'],
            $matcher->roster(
                (int) $batch->sub_institute_id,
                (int) $batch->syear,
                (int) $key['paper']['standard_id'],
                (int) $key['paper']['grade_id']
            )
        );

        $this->persist($sheet, $read, $scored, $identity);

        // Drawn from what is now STORED, not from this run's AI marks: a re-run keeps the marks a
        // teacher already set, and the marked-up copy has to show those, not the AI's.
        (new ExamSheetMarkup($annotator, $storage))->regenerate($this->sheetId);

        $this->refreshBatchCounters((int) $sheet->batch_id);
    }

    /**
     * @param  array<string,mixed>  $read
     * @param  array<string,mixed>  $scored
     * @param  array<string,mixed>  $identity
     */
    private function persist(object $sheet, array $read, array $scored, array $identity): void
    {
        $now = now();
        $maxMarks = (float) $scored['max_marks'];
        $aiTotal = (float) $scored['ai_total'];

        DB::transaction(function () use ($sheet, $read, $scored, $identity, $now, $maxMarks, $aiTotal) {
            // Re-read under a lock: the model call above took minutes, and a
            // teacher may have approved this sheet while it ran. Approved work
            // is never overwritten.
            $current = DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->lockForUpdate()->first();

            if (! $current || $current->status === 'Approved') {
                return;
            }

            // Keyed by question number: a re-run refreshes the AI's columns on
            // the rows that are already there instead of replacing them, so what
            // a teacher typed (marks, feedback) survives a re-run. Deleting and
            // re-inserting is what used to wipe it.
            $existing = DB::table('exam_evaluation_answer')
                ->where('sheet_id', $this->sheetId)
                ->get(['id', 'question_no', 'teacher_marks'])
                ->keyBy('question_no');

            $seen = [];

            foreach ($scored['answers'] as $answer) {
                $questionNo = (int) $answer['question_no'];
                $seen[] = $questionNo;

                $ai = [
                    'question_id' => (int) $answer['question_id'] ?: null,
                    'question_type' => mb_substr((string) $answer['question_type'], 0, 60),
                    'is_objective' => (bool) $answer['is_objective'],
                    'detected_answer' => (string) $answer['detected_answer'],
                    'selected_options' => mb_substr(implode(',', (array) $answer['selected_options']), 0, 100),
                    'expected_answer' => (string) $answer['expected_answer'],
                    'max_marks' => (float) $answer['max_marks'],
                    'ai_marks' => (float) $answer['ai_marks'],
                    'status' => (string) $answer['status'],
                    'ai_confidence' => (float) $answer['ai_confidence'],
                    'ai_remark' => mb_substr((string) $answer['ai_remark'], 0, 500),
                    'page' => (int) $answer['page'],
                    'box_2d' => $answer['box_2d'] ? json_encode($answer['box_2d']) : null,
                    'updated_at' => $now,
                ];

                // What the sheet calls this question. Refreshed on a re-run like the other
                // things read from the scan; the paper position (`question_no`) never changes.
                if (QuestionLabel::columnExists()) {
                    $ai['question_label'] = $answer['question_label'] ?? null;
                }

                $row = $existing->get($questionNo);

                if ($row === null) {
                    DB::table('exam_evaluation_answer')->insert($ai + [
                        'sheet_id' => $this->sheetId,
                        'batch_id' => (int) $sheet->batch_id,
                        'question_no' => $questionNo,
                        // Deliberately null. An objective mark is deterministic
                        // and a teacher will almost always accept it, but
                        // accepting is still their act.
                        'teacher_marks' => null,
                        'created_at' => $now,
                    ]);

                    continue;
                }

                // A teacher mark that no longer fits (the paper was edited and
                // the question is now worth less) is brought down to the new
                // maximum rather than left above it.
                if ($row->teacher_marks !== null && (float) $row->teacher_marks > (float) $answer['max_marks']) {
                    $ai['teacher_marks'] = (float) $answer['max_marks'];
                }

                DB::table('exam_evaluation_answer')->where('id', $row->id)->update($ai);
            }

            // Questions the paper no longer has.
            DB::table('exam_evaluation_answer')
                ->where('sheet_id', $this->sheetId)
                ->whereNotIn('question_no', $seen)
                ->delete();

            // A student a teacher has CONFIRMED -- by choosing them, or by agreeing
            // with the suggestion -- stays assigned. This is judged on the row as
            // it is NOW, under the lock, not on the copy read when the job
            // started: a teacher may have confirmed someone while the model was
            // working, and a late job must never put a different child's name on
            // the sheet. Only an unconfirmed suggestion is refreshed by a re-read.
            $keepAssigned = SheetIdentity::isConfirmed($current);

            if ($keepAssigned) {
                $studentId = (int) $current->student_id;
                $identitySource = (string) $current->identity_source;
                $identityConfidence = $current->identity_source === 'manual' ? 100 : $current->identity_confidence;
            } else {
                $studentId = $identity['student_id'];
                $identitySource = $identity['identity_source'];
                $identityConfidence = $identity['identity_confidence'];

                // One sheet per student per batch. Two scans that both read as the
                // same student leave the second unmatched for a teacher to sort
                // out, rather than putting two sets of marks on one child. A
                // confirmed assignment is exempt: it was checked against the
                // batch when the teacher confirmed it.
                if ($studentId !== null && DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $studentId)
                    ->where('id', '!=', $this->sheetId)
                    ->exists()) {
                    Log::info('Answer sheet matched a student who already has a sheet in this batch', [
                        'sheet_id' => $this->sheetId,
                    ]);

                    $studentId = null;
                    $identitySource = 'unmatched';
                }
            }

            // What the teacher saw when they confirmed is kept. The full read
            // sees the identity block again, but the confirmed record should show
            // the details that were actually reviewed, not a second reading.
            $keepDetected = $keepAssigned
                && ($current->detected_roll_no !== null && $current->detected_roll_no !== ''
                    || $current->detected_enrollment_no !== null && $current->detected_enrollment_no !== ''
                    || $current->detected_student_name !== null && $current->detected_student_name !== '');

            // Marks a teacher has already set outrank the AI's for the totals.
            $teacherTotal = null;

            if (DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->whereNotNull('teacher_marks')->exists()) {
                $teacherTotal = round((float) DB::table('exam_evaluation_answer')
                    ->where('sheet_id', $this->sheetId)
                    ->selectRaw('SUM(COALESCE(teacher_marks, ai_marks, 0)) as total')
                    ->value('total'), 2);
            }

            $effectiveTotal = $teacherTotal ?? $aiTotal;

            DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->update([
                'detected_roll_no' => $keepDetected ? $current->detected_roll_no : mb_substr((string) $read['student']['roll_no'], 0, 50),
                'detected_enrollment_no' => $keepDetected ? $current->detected_enrollment_no : mb_substr((string) $read['student']['enrollment_no'], 0, 50),
                'detected_student_name' => $keepDetected ? $current->detected_student_name : mb_substr((string) $read['student']['name'], 0, 150),
                'student_id' => $studentId,
                'identity_confidence' => $identityConfidence,
                'identity_source' => $identitySource,
                // An unmatched sheet needs a teacher whatever the marks look
                // like -- marks with nobody to put them against are not a result.
                'status' => ($scored['needs_review'] || $studentId === null)
                    ? 'Needs review'
                    : 'Evaluated',
                'ai_total' => $aiTotal,
                'teacher_total' => $teacherTotal,
                'max_marks' => $maxMarks,
                'percentage' => $maxMarks > 0 ? round($effectiveTotal / $maxMarks * 100, 2) : null,
                // The identity stage's notes (why a match was withheld, where on
                // the file the details were) stay alongside the result.
                'ai_result_json' => SheetIdentity::withIdentityBlock($current->ai_result_json, [
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
            // A sheet waiting for a teacher to confirm its student is not being
            // worked on: counting it as in flight would leave the batch showing
            // "Processing" until someone acted, as if the system were busy.
            ->selectRaw("SUM(CASE WHEN status IN ('Evaluated','Needs review','Approved','Failed','Awaiting identity') THEN 1 ELSE 0 END) as settled")
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

        // Only a sheet still waiting on this job can fail because of it. One a
        // teacher has since approved (or that another run finished) is not
        // touched by a late, stale failure.
        DB::table('exam_evaluation_sheet')
            ->where('id', $this->sheetId)
            ->whereIn('status', ['Pending', 'Processing'])
            ->update([
                'status' => 'Failed',
                'failure_reason' => mb_substr($reason, 0, 250),
                'evaluated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($batchId > 0) {
            self::refreshBatch($batchId);
        }
    }
}
