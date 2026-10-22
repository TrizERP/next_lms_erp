<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Jobs\IdentifyAnswerSheetJob;
use App\Services\Evaluation\SheetIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A sheet whose job never finishes -- a worker that died, or was never started -- used to sit
 * in "Identifying" (or "Processing") for good, and a re-run was refused as "already being
 * worked on". After SheetIdentity::STALLED_AFTER_MINUTES with no change it is flagged
 * `stalled` and may be put back on the queue by a teacher; before that it is left alone,
 * because a slow job is not a lost one.
 */
class StalledSheetRecoveryTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch(['status' => 'Processing']);
        Queue::fake();
    }

    /** Backdates the sheet's last change by this many minutes. */
    private function untouchedFor(int $sheetId, int $minutes): void
    {
        DB::table('exam_evaluation_sheet')->where('id', $sheetId)->update(['updated_at' => now()->subMinutes($minutes)]);
    }

    private function listed(int $sheetId): array
    {
        return collect($this->getJson("/api/exam-evaluation/batches/{$this->batchId}?sub_institute_id=" . self::TENANT)->json('data.sheets'))->firstWhere('id', $sheetId);
    }

    private function rerun(int $sheetId)
    {
        return $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body());
    }

    // -- what counts as stalled -------------------------------------------------------

    /** @return array<string,array{0:string}> */
    public static function inFlight(): array
    {
        return ['identifying' => ['Identifying'], 'queued' => ['Pending'], 'processing' => ['Processing']];
    }

    #[DataProvider('inFlight')]
    public function test_a_sheet_the_system_is_working_on_is_only_stalled_after_the_threshold(string $status): void
    {
        $sheetId = $this->bareSheet($this->batchId, ['status' => $status]);

        $this->untouchedFor($sheetId, SheetIdentity::STALLED_AFTER_MINUTES - 1);
        $this->assertFalse($this->listed($sheetId)['stalled'], 'slow is not lost');

        $this->untouchedFor($sheetId, SheetIdentity::STALLED_AFTER_MINUTES + 1);
        $this->assertTrue($this->listed($sheetId)['stalled']);
    }

    public function test_the_threshold_is_longer_than_the_slowest_honest_run_of_the_identity_job(): void
    {
        $job = new IdentifyAnswerSheetJob(1);
        // Every try runs to its timeout, with the backoff between tries: the worst a healthy queue can do.
        $worstCaseSeconds = $job->tries * $job->timeout + array_sum($job->backoff());

        $this->assertGreaterThan($worstCaseSeconds, SheetIdentity::STALLED_AFTER_MINUTES * 60);
    }

    /** @return array<string,array{0:string}> */
    public static function notBeingWorkedOn(): array
    {
        return [
            'waiting for a teacher' => ['Awaiting identity'],
            'needs review' => ['Needs review'],
            'evaluated' => ['Evaluated'],
            'approved' => ['Approved'],
            'failed' => ['Failed'],
        ];
    }

    #[DataProvider('notBeingWorkedOn')]
    public function test_a_sheet_that_is_waiting_for_a_person_or_finished_is_never_stalled_however_old(string $status): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => $status]);
        $this->untouchedFor($sheetId, 24 * 60 * 5);

        $this->assertFalse($this->listed($sheetId)['stalled']);
    }

    public function test_a_sheet_with_no_timestamp_is_not_called_stalled(): void
    {
        $this->assertFalse(SheetIdentity::isStalled(['status' => 'Identifying', 'updated_at' => null]));
        $this->assertFalse(SheetIdentity::isStalled(['status' => 'Identifying', 'updated_at' => 'not a date']));
    }

    // -- putting it back on the queue ---------------------------------------------------

    public function test_a_recent_in_flight_sheet_still_cannot_be_re_run(): void
    {
        $reading = $this->identifyingSheet($this->batchId);
        $marking = $this->makeSheet($this->batchId, ['status' => 'Processing', 'student_id' => 102]);

        $this->rerun($reading)->assertStatus(422)->assertJsonPath('message', 'This sheet is already being read. Wait for it to finish before reading it again.');
        $this->rerun($marking)->assertStatus(422)->assertJsonPath('message', 'This sheet is already being evaluated. Wait for it to finish before re-running it.');

        Queue::assertNothingPushed();
    }

    public function test_a_stalled_identification_is_put_back_on_the_queue_and_the_attempt_is_on_record(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $this->untouchedFor($sheetId, SheetIdentity::STALLED_AFTER_MINUTES + 5);

        $this->rerun($sheetId)->assertOk();

        Queue::assertPushed(IdentifyAnswerSheetJob::class, 1);
        Queue::assertNotPushed(EvaluateAnswerSheetJob::class);

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Identifying', $sheet->status);
        $this->assertFalse($this->listed($sheetId)['stalled'], 'touched again, so no longer stalled');

        $audit = json_decode(DB::table('platform_audit_log')->where('action', 'reprocess')->value('before_json'), true);
        $this->assertSame(['status' => 'Identifying', 'stalled' => true], $audit);
    }

    public function test_a_stalled_evaluation_is_re_queued_for_marking_and_keeps_its_confirmed_student(): void
    {
        $sheetId = $this->makeSheet($this->batchId, ['status' => 'Processing', 'student_id' => 102]);
        $this->untouchedFor($sheetId, SheetIdentity::STALLED_AFTER_MINUTES + 5);

        $this->rerun($sheetId)->assertOk();

        Queue::assertPushed(EvaluateAnswerSheetJob::class, 1);
        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Pending', $sheet->status);
        $this->assertSame(102, (int) $sheet->student_id);
        $this->assertNotNull($sheet->identity_confirmed_at);
    }

    public function test_recovery_does_not_get_around_the_other_guards(): void
    {
        $published = $this->makeBatch(['status' => 'Published']);
        $stuckThere = $this->bareSheet($published, ['status' => 'Identifying']);
        $approved = $this->makeSheet($this->batchId, ['status' => 'Approved']);
        $this->untouchedFor($stuckThere, 600);
        $this->untouchedFor($approved, 600);

        $this->rerun($stuckThere)->assertStatus(422);
        $this->rerun($approved)->assertStatus(422);

        Queue::assertNothingPushed();
        $this->assertSame('Identifying', DB::table('exam_evaluation_sheet')->find($stuckThere)->status);
    }

    // -- a scan that is not on this machine ----------------------------------------------------

    private function storageWithNoScan(): \App\Services\Evaluation\ExamEvaluationStorage
    {
        return new class extends \App\Services\Evaluation\ExamEvaluationStorage {
            public function sheetPath(string $fileName): ?string
            {
                return null;
            }
        };
    }

    public function test_a_sheet_whose_scan_is_not_on_this_machine_fails_with_what_to_do_about_it(): void
    {
        // The database is shared and the files are not: a sheet uploaded from another machine has no scan here.
        $reading = $this->identifyingSheet($this->batchId);
        $marking = $this->bareSheet($this->batchId, ['status' => 'Pending', 'student_id' => 102, 'identity_source' => 'manual', 'identity_confirmed_at' => now()]);

        $this->identify($reading, new \Tests\Support\FakeGeminiClient([]), null, $this->storageWithNoScan());
        $this->assertSame('Failed', DB::table('exam_evaluation_sheet')->find($reading)->status);

        $annotator = new class extends \App\Services\Homework\HomeworkAnnotatedPdfService {
        };
        (new EvaluateAnswerSheetJob($marking))->handle(
            app(\App\Services\Evaluation\AnswerKeyService::class),
            new \App\Services\Evaluation\AnswerSheetReaderService(new \Tests\Support\FakeGeminiClient([])),
            new \App\Services\Evaluation\AnswerSheetScoringService(new \Tests\Support\FakeGeminiClient([])),
            new \App\Services\Evaluation\StudentMatcherService(),
            $annotator,
            $this->storageWithNoScan()
        );

        foreach ([$reading, $marking] as $sheetId) {
            $reason = DB::table('exam_evaluation_sheet')->find($sheetId)->failure_reason;

            $this->assertSame(SheetIdentity::MISSING_FILE_MESSAGE, $reason);
            $this->assertStringContainsString('upload it again', $reason);
        }

        $this->assertLessThanOrEqual(250, strlen(SheetIdentity::MISSING_FILE_MESSAGE), 'must fit failure_reason');
        $this->assertSame(102, (int) DB::table('exam_evaluation_sheet')->find($marking)->student_id, 'a confirmed student is untouched');
    }

    public function test_such_a_sheet_can_be_removed_so_it_can_be_uploaded_again(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $this->identify($sheetId, new \Tests\Support\FakeGeminiClient([]), null, $this->storageWithNoScan());

        $this->deleteJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk();

        $this->assertNull(DB::table('exam_evaluation_sheet')->find($sheetId));
    }

    public function test_the_stalled_flag_travels_with_every_sheet_payload(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);
        $this->untouchedFor($sheetId, SheetIdentity::STALLED_AFTER_MINUTES + 5);

        $this->assertTrue($this->listed($sheetId)['stalled']);
        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)->assertOk()->assertJsonPath('data.sheet.stalled', true);
    }
}
