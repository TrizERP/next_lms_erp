<?php

namespace App\Jobs;

use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Evaluation\QueueTiming;
use App\Services\Evaluation\SheetIdentity;
use App\Services\Evaluation\StudentMatcherService;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\ProviderConfigurationException;
use App\Services\Homework\Exceptions\RateLimitedException;
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
 * Stage one of an answer sheet's life: work out whose it is, and stop.
 *
 * An uploaded scan is read for the identity block only, matched against the
 * students eligible for the exam, and left in `Awaiting identity` with the
 * result as a SUGGESTION. No marking happens until a teacher has confirmed the
 * student (or chosen another), which is what starts EvaluateAnswerSheetJob. That
 * ordering is the point: a misread roll number costs one cheap read and a
 * teacher's glance, not a full evaluation filed under the wrong child.
 *
 * What this job never does:
 *  - it never confirms anything. `identity_confirmed_*` is written only by a
 *    teacher, through the review endpoints;
 *  - it never overwrites an assignment that has been confirmed. The sheet is
 *    re-read under a lock before it is written, and one that is no longer
 *    `Identifying` (a teacher got there first, or a duplicate job ran) is left
 *    exactly as it is;
 *  - it never fails a sheet because the identity could not be read. An unreadable
 *    block is an ordinary case -- the teacher picks the student by hand -- so it
 *    ends in `Awaiting identity` with the reason recorded, not in `Failed`.
 *    A sheet fails here only when it cannot be opened at all, or when the AI
 *    service itself is unusable (a rejected key, a spent allowance): that is not
 *    about the scan, would repeat for every sheet, and is shown with its real reason.
 */
class IdentifyAnswerSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Waits out a busy provider or a per-minute rate limit; see backoff(). */
    public int $tries = 4;

    /**
     * Two model calls at most (the second only to re-ask after unparseable JSON), each up to
     * the document client's 90s, plus a fallback attempt when the first model is busy:
     * about 200s in the worst case. A queue's retry_after must be longer than this --
     * see QueueTiming and docs/EXAM_EVALUATION_DEPLOYMENT_RUNBOOK.md.
     */
    public int $timeout = 300;

    public function __construct(private readonly int $sheetId)
    {
    }

    /** @return array<int,int> Seconds before each retry. */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * One run per sheet at a time: a second dispatch for a sheet already being
     * read (a double click on "Read again") is dropped, not queued behind it.
     *
     * @return array<int,object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('identify-' . $this->sheetId))->dontRelease()->expireAfter($this->timeout + 60)];
    }

    public function handle(
        AnswerSheetReaderService $reader,
        StudentMatcherService $matcher,
        ExamEvaluationStorage $storage
    ): void {
        QueueTiming::warnIfLeaseIsShorterThanTimeout(self::class, $this->timeout, $this->job?->getConnectionName() ?? $this->connection);

        $sheet = DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->first();

        if (! $sheet) {
            return;
        }

        // Only a sheet still waiting to be identified is touched. Anything past
        // that -- confirmed, evaluating, approved -- is a stale or duplicate job
        // and must not change who the sheet belongs to.
        if ($sheet->status !== SheetIdentity::STATUS_IDENTIFYING) {
            Log::info('Answer sheet identification skipped; sheet is not waiting for it', [
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

        $scope = $matcher->paperScope((int) $batch->sub_institute_id, (int) $batch->question_paper_id);

        if ($scope === null) {
            $this->markFailed('The exam this evaluation was created for could not be found.');

            return;
        }

        $absolutePath = $storage->sheetPath((string) $sheet->file_name);

        if ($absolutePath === null) {
            $this->markFailed(SheetIdentity::MISSING_FILE_MESSAGE);

            return;
        }

        $read = null;
        $readFailed = false;

        try {
            $read = $reader->readIdentity($absolutePath, (string) ($sheet->mime_type ?: 'application/pdf'), $scope);
        } catch (DocumentExtractionException $exception) {
            // A key the provider refuses, or an allowance that is spent, is not about THIS
            // scan: every sheet will fail the same way until an administrator fixes it. Calling
            // it "unreadable" would send the teacher to pick students by hand while hiding
            // the real cause until marking failed, so the sheet fails here with the reason and
            // can be read again once the service is fixed. (The reader keeps the provider's
            // exception as `previous`; a plain unreadable scan has none of these.)
            $cause = $exception->getPrevious();

            if ($cause instanceof ProviderConfigurationException || $cause instanceof ModelQuotaExhaustedException) {
                Log::warning('Answer sheet identification stopped: the AI service is not usable', [
                    'sheet_id' => $this->sheetId,
                    'message' => $exception->getMessage(),
                ]);
                $this->markFailed($exception->getMessage());

                return;
            }

            // The file opened but its identity could not be read. Not an error
            // for the teacher to fix by re-uploading: they pick the student.
            Log::info('Answer sheet identity could not be read', [
                'sheet_id' => $this->sheetId,
                'message' => $exception->getMessage(),
            ]);
            $readFailed = true;
        } catch (ProviderBusyException|RateLimitedException|ConnectionException $exception) {
            // Not the sheet's fault. Raised to the queue, which retries after
            // backoff(); if every try is spent failed() settles the sheet.
            Log::info('Answer sheet identification deferred; provider unavailable', [
                'sheet_id' => $this->sheetId,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $roster = $matcher->roster(
            (int) $batch->sub_institute_id,
            (int) $batch->syear,
            $scope['standard_id'],
            $scope['grade_id']
        );

        $student = $read['student'] ?? ['roll_no' => '', 'enrollment_no' => '', 'name' => '', 'confidence' => 0.0];

        if ($readFailed) {
            $match = $this->unmatched(SheetIdentity::ISSUE_UNREADABLE);
        } elseif ($read['multiple_students']) {
            // One file that carries two students' details cannot be filed under
            // either; it needs a person to look at it.
            $match = $this->unmatched(SheetIdentity::ISSUE_MULTIPLE_STUDENTS);
        } else {
            $match = $matcher->match($student, $roster);
        }

        $this->persist($sheet, $student, (int) ($read['page'] ?? 0), $match);
        EvaluateAnswerSheetJob::refreshBatch((int) $sheet->batch_id);
    }

    /**
     * @param  array{roll_no:string, enrollment_no:string, name:string, confidence:float}  $student
     * @param  array<string,mixed>  $match
     */
    private function persist(object $sheet, array $student, int $page, array $match): void
    {
        DB::transaction(function () use ($sheet, $student, $page, $match) {
            // Serialises identification within a batch, so two sheets that both
            // read as the same student cannot both be handed that student.
            DB::table('exam_evaluation_batch')->where('id', $sheet->batch_id)->lockForUpdate()->first();

            // Re-read under the lock: the model call took time, and a teacher
            // may have confirmed or reassigned this sheet while it ran.
            $current = DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->lockForUpdate()->first();

            if (! $current || $current->status !== SheetIdentity::STATUS_IDENTIFYING) {
                return;
            }

            $studentId = $match['student_id'];
            $source = (string) $match['identity_source'];
            $issue = $match['issue'];
            $candidates = $match['candidates'];
            $duplicateOf = null;

            // One sheet per student per batch. A suggestion for a student who
            // already holds another sheet is withdrawn and said so, instead of
            // quietly offering the same child twice.
            if ($studentId !== null) {
                $duplicateOf = DB::table('exam_evaluation_sheet')
                    ->where('batch_id', $sheet->batch_id)
                    ->where('student_id', $studentId)
                    ->where('id', '!=', $this->sheetId)
                    ->value('id');

                if ($duplicateOf !== null) {
                    Log::info('Answer sheet matched a student who already has a sheet in this batch', [
                        'sheet_id' => $this->sheetId,
                        'other_sheet_id' => (int) $duplicateOf,
                    ]);

                    $candidates = [(int) $studentId];
                    $studentId = null;
                    $source = 'unmatched';
                    $issue = SheetIdentity::ISSUE_DUPLICATE;
                }
            }

            DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->update([
                'detected_roll_no' => mb_substr((string) $student['roll_no'], 0, 50),
                'detected_enrollment_no' => mb_substr((string) $student['enrollment_no'], 0, 50),
                'detected_student_name' => mb_substr((string) $student['name'], 0, 150),
                'student_id' => $studentId,
                'identity_source' => $source,
                'identity_confidence' => $studentId !== null || $issue !== null ? $match['identity_confidence'] : null,
                'status' => SheetIdentity::STATUS_AWAITING,
                'ai_result_json' => json_encode([
                    'student' => $student,
                    'identity' => array_filter([
                        'issue' => $issue,
                        'candidates' => $candidates,
                        'page' => $page > 0 ? $page : null,
                        'duplicate_of_sheet_id' => $duplicateOf !== null ? (int) $duplicateOf : null,
                    ], static fn ($value) => $value !== null && $value !== []),
                ]),
                'failure_reason' => null,
                'updated_at' => now(),
            ] + SheetIdentity::clearedConfirmation());
        });
    }

    /** @return array<string,mixed> */
    private function unmatched(string $issue): array
    {
        return [
            'student_id' => null,
            'identity_source' => 'unmatched',
            'identity_confidence' => 0.0,
            'issue' => $issue,
            'candidates' => [],
        ];
    }

    /**
     * Every try is spent. The sheet is not failed -- the teacher can still pick
     * the student by hand and have it evaluated -- so it is handed over with the
     * reason "could not be read just now".
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('IdentifyAnswerSheetJob gave up', [
            'sheet_id' => $this->sheetId,
            'message' => $exception->getMessage(),
        ]);

        $handedOver = DB::table('exam_evaluation_sheet')
            ->where('id', $this->sheetId)
            ->where('status', SheetIdentity::STATUS_IDENTIFYING)
            ->update([
                'status' => SheetIdentity::STATUS_AWAITING,
                'student_id' => null,
                'identity_source' => 'unmatched',
                'ai_result_json' => json_encode(['identity' => ['issue' => SheetIdentity::ISSUE_UNAVAILABLE]]),
                'failure_reason' => null,
                'updated_at' => now(),
            ] + SheetIdentity::clearedConfirmation());

        $batchId = (int) DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->value('batch_id');

        if ($handedOver && $batchId > 0) {
            EvaluateAnswerSheetJob::refreshBatch($batchId);
        }
    }

    private function markFailed(string $reason): void
    {
        $batchId = (int) DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->value('batch_id');

        DB::table('exam_evaluation_sheet')
            ->where('id', $this->sheetId)
            ->where('status', SheetIdentity::STATUS_IDENTIFYING)
            ->update([
                'status' => 'Failed',
                'failure_reason' => mb_substr($reason, 0, 250),
                'evaluated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($batchId > 0) {
            EvaluateAnswerSheetJob::refreshBatch($batchId);
        }
    }
}
