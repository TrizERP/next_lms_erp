<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\EvaluateAnswerSheetJob;
use App\Services\Evaluation\ExamEvaluationStorage;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\GeminiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeGeminiClient;

/**
 * Retry, backoff and give-up behaviour, observed on the REAL database queue and
 * the REAL `queue:work` worker (in-memory sqlite), with time moved forward by hand.
 * Only the AI provider is faked, so this is evidence of what a deployed worker does
 * with EvaluateAnswerSheetJob, not of what the job's own code intends.
 */
class QueueRetryBehaviourTest extends ExamEvaluationTestCase
{
    private int $sheetId;

    private string $scan = '';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['2026_08_20_000001_create_jobs_table', '2019_08_19_000000_create_failed_jobs_table'] as $migration) {
            (require base_path("database/migrations/{$migration}.php"))->up();
        }

        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.retry_after' => 660,
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'sqlite',
            'queue.failed.table' => 'failed_jobs',
        ]);

        $this->seedPaper();
        $batchId = $this->makeBatch(['status' => 'Processing']);
        $this->sheetId = $this->makeSheet($batchId, ['status' => 'Pending', 'student_id' => null, 'identity_source' => null, 'ai_total' => null, 'percentage' => null]);
        DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->delete();

        $this->scan = (string) tempnam(sys_get_temp_dir(), 'exam-eval-queue-');
        file_put_contents($this->scan, '%PDF-1.4 test scan');
        $scan = $this->scan;

        $this->app->instance(ExamEvaluationStorage::class, new class($scan) extends ExamEvaluationStorage {
            public function __construct(private string $path)
            {
            }

            public function sheetPath(string $fileName): ?string
            {
                return $this->path;
            }

            public function putAnnotated(int $sheetId, string $pdfBinary): string
            {
                return 'annotated-test.pdf';
            }
        });

        Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink($this->scan);
        parent::tearDown();
    }

    /** @param array<int,mixed> $replies */
    private function provider(array $replies): FakeGeminiClient
    {
        $fake = new FakeGeminiClient($replies);
        $this->app->instance(GeminiClient::class, $fake);

        return $fake;
    }

    /** One pass of the real worker over whatever is due. */
    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--queue' => 'default'])->assertExitCode(0);
    }

    private function queued(): ?object
    {
        return DB::table('jobs')->first();
    }

    private function sheet(): object
    {
        return DB::table('exam_evaluation_sheet')->find($this->sheetId);
    }

    private function readerReply(): array
    {
        return [
            'student' => ['roll_no' => '1', 'enrollment_no' => 'GR101', 'name' => 'Asha Patel', 'confidence' => 90],
            'responses' => [
                ['question_no' => 1, 'attempted' => true, 'selected_options' => ['A'], 'answer_text' => '', 'page' => 1, 'box_2d' => null],
                ['question_no' => 2, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'Plants use sunlight', 'page' => 1, 'box_2d' => null],
                ['question_no' => 3, 'attempted' => true, 'selected_options' => [], 'answer_text' => 'speeds a reaction', 'page' => 1, 'box_2d' => null],
            ],
        ];
    }

    private function graderReply(): array
    {
        return ['results' => [['question_no' => 2, 'marks' => 3, 'confidence' => 80, 'remark' => 'Two points made.']]];
    }

    public function test_a_provider_that_stays_busy_is_retried_on_the_backoff_schedule_then_failed_once(): void
    {
        $busy = fn () => new ProviderBusyException('The AI model was busy at the provider.');
        $fake = $this->provider([$busy(), $busy(), $busy(), $busy()]);

        EvaluateAnswerSheetJob::dispatch($this->sheetId);
        $this->assertSame(1, DB::table('jobs')->count());

        // Attempt 1: fails, goes back on the queue to be tried again in 60 seconds.
        $this->work();
        $row = $this->queued();
        $this->assertNotNull($row, 'the job must still be queued for a retry');
        $this->assertSame(1, (int) $row->attempts);
        $this->assertSame(Carbon::now()->timestamp + 60, (int) $row->available_at, 'first retry after 60s');
        $this->assertSame('Processing', $this->sheet()->status, 'not failed while retries remain');
        $this->assertSame(0, DB::table('failed_jobs')->count());

        // Too early: nothing is due, so the worker does nothing and the provider is not asked again.
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $this->work();
        $this->assertCount(1, $fake->calls, 'a retry must not run before its backoff has passed');

        // Attempt 2 -> retry in 180s.
        Carbon::setTestNow(Carbon::now()->addSeconds(31));
        $this->work();
        $row = $this->queued();
        $this->assertSame(2, (int) $row->attempts);
        $this->assertSame(Carbon::now()->timestamp + 180, (int) $row->available_at, 'second retry after 180s');

        // Attempt 3 -> retry in 420s.
        Carbon::setTestNow(Carbon::now()->addSeconds(181));
        $this->work();
        $row = $this->queued();
        $this->assertSame(3, (int) $row->attempts);
        $this->assertSame(Carbon::now()->timestamp + 420, (int) $row->available_at, 'third retry after 420s');

        // Attempt 4 is the last: the job is given up on, once.
        Carbon::setTestNow(Carbon::now()->addSeconds(421));
        $this->work();

        $this->assertNull($this->queued(), 'the exhausted job leaves the queue');
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'and is recorded as failed exactly once');
        $this->assertCount(4, $fake->calls, 'four tries, no more');

        $sheet = $this->sheet();
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('busy', $sheet->failure_reason);
        $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->count(), 'no half-written answers');
    }

    public function test_a_hiccup_that_clears_on_the_second_try_ends_in_a_normal_evaluation_with_no_duplicates(): void
    {
        $fake = $this->provider([new RateLimitedException('Rate limit reached.'), $this->readerReply(), $this->graderReply()]);

        EvaluateAnswerSheetJob::dispatch($this->sheetId);

        $this->work();
        $this->assertSame('Processing', $this->sheet()->status);
        $this->assertSame(1, (int) $this->queued()->attempts);

        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $this->work();

        $this->assertNull($this->queued(), 'a finished job is removed from the queue');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $sheet = $this->sheet();
        $this->assertSame('Needs review', $sheet->status);
        $this->assertSame(101, (int) $sheet->student_id);
        $this->assertSame(3, DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->count(), 'one row per question, not one per attempt');
        $this->assertSame([null, null, null], DB::table('exam_evaluation_answer')->where('sheet_id', $this->sheetId)->orderBy('question_no')->pluck('teacher_marks')->all(), 'the AI never writes a teacher mark');
        $this->assertCount(3, $fake->calls);
    }

    public function test_a_spent_daily_quota_is_not_retried(): void
    {
        $fake = $this->provider([new ModelQuotaExhaustedException('The daily allowance for the model is spent.')]);

        EvaluateAnswerSheetJob::dispatch($this->sheetId);
        $this->work();

        $this->assertNull($this->queued(), 'waiting will not help, so it is not queued again');
        $this->assertCount(1, $fake->calls);
        $this->assertSame('Failed', $this->sheet()->status);
        $this->assertStringContainsString('daily allowance', $this->sheet()->failure_reason);
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'handled in the job: a reason on the sheet, not a queue failure');
    }

    public function test_a_second_dispatch_for_a_sheet_already_being_read_is_dropped_not_run(): void
    {
        $fake = $this->provider([]);
        // Another worker is mid-run on this sheet: it holds the lock the job's middleware takes.
        $held = Cache::lock('laravel-queue-overlap:' . EvaluateAnswerSheetJob::class . ':' . $this->sheetId, 120);
        $this->assertTrue($held->get());

        EvaluateAnswerSheetJob::dispatch($this->sheetId);
        $this->work();

        $this->assertSame([], $fake->calls, 'the duplicate must not call the provider');
        $this->assertNull($this->queued(), 'and is dropped rather than left to pile up');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame('Pending', $this->sheet()->status, 'the in-flight run owns the sheet');

        $held->release();
    }

    public function test_a_redelivered_job_for_a_sheet_a_teacher_has_since_approved_changes_nothing(): void
    {
        $fake = $this->provider([]);
        DB::table('exam_evaluation_sheet')->where('id', $this->sheetId)->update(['status' => 'Approved', 'approved_by' => self::TEACHER, 'teacher_total' => 9]);

        EvaluateAnswerSheetJob::dispatch($this->sheetId);
        $this->work();

        $this->assertSame([], $fake->calls);
        $this->assertNull($this->queued());
        $sheet = $this->sheet();
        $this->assertSame('Approved', $sheet->status);
        $this->assertEquals(9.0, $sheet->teacher_total);
    }
}
