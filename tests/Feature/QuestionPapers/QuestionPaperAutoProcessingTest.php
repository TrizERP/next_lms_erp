<?php

namespace Tests\Feature\QuestionPapers;

use App\Http\Controllers\api\Platform\QuestionPaperController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Automatic background processing, proven with a REAL worker process.
 *
 * A separate PHP process (tests/Support/question_paper_worker.php) runs the production worker loop
 * (`queue:work question_papers --queue=question-papers`) against a SCRATCH sqlite file. The test never starts a
 * worker per job, never calls queue:work itself and never runs a job in-process: it uploads through the real
 * controller and waits for the worker that is already running to finish the job. The AI parser and Spaces are
 * replaced by local fakes, so this proves the plumbing (dispatch -> queue table -> long-running worker ->
 * persisted results -> notification), not AI quality.
 *
 * Nothing here can reach the shared database: the application in the test process and in the worker are both
 * pinned to a qp-worker-*.sqlite file in the system temp directory (the worker refuses anything else).
 */
class QuestionPaperAutoProcessingTest extends QuestionPaperTestCase
{
    private ?string $workerDb = null;
    private string $scratch = '';

    /** @var list<array{process: resource, pid: int}> */
    private array $workers = [];

    protected function setUp(): void
    {
        // The worker process runs on a scratch sqlite FILE; this class is about process plumbing, not the database
        // engine, so it does not also run under the MariaDB switch (the rest of the suite does).
        if (getenv('QP_MYSQL_PORT')) {
            $this->markTestSkipped('real-worker tests run on the scratch sqlite file only');
        }
        parent::setUp();
    }

    private static function heartbeatKey(string $scratch): string
    {
        // Unique per test run, and never the key a real worker uses.
        return 'qp-test-heartbeat:' . basename($scratch);
    }

    protected function sqliteDatabase(): string
    {
        if ($this->workerDb === null) {
            $this->scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qp-worker-' . bin2hex(random_bytes(5));
            mkdir($this->scratch);
            $this->workerDb = $this->scratch . DIRECTORY_SEPARATOR . 'qp-worker-' . bin2hex(random_bytes(4)) . '.sqlite';
            touch($this->workerDb);
            $this->fileDir = $this->scratch;
        }

        return $this->workerDb;
    }

    protected function createSurroundingTables(): void
    {
        // Both processes share this file: WAL lets the worker write while the test reads.
        DB::select('PRAGMA journal_mode=WAL');
        parent::createSurroundingTables();
        // The heartbeat is read from the same file cache the worker process writes (a unique key, removed in tearDown).
        config(['cache.default' => 'file', 'idms.question_paper.heartbeat_key' => self::heartbeatKey($this->scratch), 'idms.question_paper.heartbeat_trusted' => true]);
        (require base_path('database/migrations/2026_08_20_000001_create_jobs_table.php'))->up();
        (require base_path('database/migrations/2019_08_19_000000_create_failed_jobs_table.php'))->up();
        // The application reads this while booting its routes (the worker boots the same app).
        \Illuminate\Support\Facades\Schema::create('custom_module_tables', function ($t) {
            $t->increments('id');
            $t->string('module_name')->nullable();
            $t->string('access_link')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            $this->stopProcess($worker['process']);
        }
        $this->workers = [];
        // A skipped test (the MariaDB run) never built the scratch folder or the app, so there is nothing to clean.
        if ($this->scratch !== '') {
            \Illuminate\Support\Facades\Cache::forget(self::heartbeatKey($this->scratch));
        }
        parent::tearDown();
        if ($this->scratch !== '' && is_dir($this->scratch)) {
            foreach (glob($this->scratch . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->scratch);
        }
    }

    /* ---------- helpers ---------- */

    private function fixture(array $questions): void
    {
        file_put_contents($this->scratch . DIRECTORY_SEPARATOR . 'parser.json', json_encode(['header' => [], 'questions' => $questions]));
    }

    private function questions(string $prefix): array
    {
        return [
            $this->mcq("{$prefix}: which state of matter has a definite shape?", 'A'),
            $this->mcq("{$prefix}: which state of matter has no fixed volume?", 'C'),
            ['item_number' => '3', 'page' => 1, 'format' => 'short', 'question_text' => "{$prefix}: define evaporation.", 'answer_text' => 'Liquid to vapour.', 'marks' => 2],
        ];
    }

    /** @return array{process: resource, pid: int} */
    private function startWorker(): array
    {
        $ready = $this->scratch . DIRECTORY_SEPARATOR . 'ready-' . count($this->workers);
        @unlink($ready);
        $specFile = $this->scratch . DIRECTORY_SEPARATOR . 'spec-' . count($this->workers) . '.json';
        file_put_contents($specFile, json_encode([
            'db' => str_replace('\\', '/', $this->workerDb),
            'files_dir' => $this->scratch,
            'parser_file' => $this->scratch . DIRECTORY_SEPARATOR . 'parser.json',
            'ready' => $ready,
            'cache_driver' => 'file',
            'heartbeat_key' => self::heartbeatKey($this->scratch),
        ]));
        $log = $this->scratch . DIRECTORY_SEPARATOR . 'worker-' . count($this->workers) . '.log';

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/question_paper_worker.php'), $specFile],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            base_path()
        );
        $this->assertIsResource($process, 'the worker process could not be started');

        $this->waitFor(fn () => is_file($ready) || !proc_get_status($process)['running'], 40, 'the worker never announced itself');
        $this->assertTrue(proc_get_status($process)['running'], 'the worker exited at start-up: ' . (is_file($log) ? substr((string) file_get_contents($log), -600) : ''));

        $worker = ['process' => $process, 'pid' => (int) file_get_contents($ready)];
        $this->workers[] = $worker;

        return $worker;
    }

    private function stopProcess($process): void
    {
        if (is_resource($process)) {
            @proc_terminate($process);
            @proc_close($process);
        }
    }

    private function waitFor(callable $condition, int $seconds, string $failure): void
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            if ($condition()) {
                return;
            }
            usleep(300_000);
        }
        $this->fail($failure);
    }

    private function jobIsFinished(string $uuid): bool
    {
        return in_array(DB::table('question_paper_jobs')->where('uuid', $uuid)->value('status'), ['completed', 'completed_with_warnings', 'failed'], true);
    }

    private function uploadPaper(string $name = 'Science paper.pdf', ?string $scheduledAt = null): array
    {
        $path = $this->scratch . DIRECTORY_SEPARATOR . 'upload-' . bin2hex(random_bytes(3)) . '.pdf';
        file_put_contents($path, TinyPdf::make(["Class 9 Science question paper with enough text to be a real document, page one: {$name}."]));
        $params = ['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101];
        if ($scheduledAt !== null) {
            $params['scheduled_at'] = $scheduledAt;
        }
        $request = $this->authed('POST', $params);
        $request->files->set('file', new UploadedFile($path, $name, 'application/pdf', null, true));

        $response = app(QuestionPaperController::class)->store($request);
        $this->assertSame(201, $response->getStatusCode());

        return $response->getData(true)['data'];
    }

    private function sentinelOnDefaultQueue(): int
    {
        return (int) DB::table('jobs')->insertGetId([
            'queue' => 'default', 'payload' => json_encode(['displayName' => 'Sentinel', 'job' => 'Sentinel', 'data' => []]),
            'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
        ]);
    }

    /* ---------- the tests ---------- */

    public function test_a_running_worker_processes_every_new_upload_automatically_and_leaves_the_default_queue_alone(): void
    {
        $this->fixture($this->questions('First'));
        $worker = $this->startWorker();
        $sentinel = $this->sentinelOnDefaultQueue();

        // Upload 1: the API answers as soon as the job is stored and queued; extraction has not happened yet.
        $first = $this->uploadPaper('first.pdf');
        $this->assertSame('queued', $first['status']);
        $this->assertSame(0, DB::table('lms_question_master')->count(), 'nothing is extracted inside the request');

        $this->waitFor(fn () => $this->jobIsFinished($first['id']), 60, 'the running worker did not process upload 1 by itself');
        $one = DB::table('question_paper_jobs')->where('uuid', $first['id'])->first();
        $this->assertSame('completed', $one->status);
        $this->assertSame([3, 3], [(int) $one->questions_extracted, (int) $one->questions_saved]);
        $this->assertSame(3, DB::table('lms_question_master')->where('status', 1)->count());
        $this->assertSame(1, DB::table('user_notifications')->count());
        $this->assertNull($one->lease_token);

        // Upload 2, later, with the SAME worker process still running: also picked up, with no command run by anyone.
        $this->fixture($this->questions('Second'));
        $second = $this->uploadPaper('second.pdf');
        $this->assertNotSame($first['id'], $second['id']);
        $this->waitFor(fn () => $this->jobIsFinished($second['id']), 60, 'the running worker did not process upload 2 by itself');

        $this->assertSame('completed', DB::table('question_paper_jobs')->where('uuid', $second['id'])->value('status'));
        $this->assertSame(6, DB::table('lms_question_master')->count());
        $this->waitFor(fn () => DB::table('user_notifications')->count() === 2, 10, 'the second completion notice was not saved');
        $this->assertSame(2, DB::table('user_notifications')->count(), 'one completion notice per paper');
        $this->assertTrue(proc_get_status($worker['process'])['running'], 'the same worker process handled both uploads');

        // Only this feature's queue was consumed.
        $this->waitFor(fn () => DB::table('jobs')->where('queue', 'question-papers')->doesntExist(), 10, 'the queue row was not deleted after processing');
        $this->assertSame(0, DB::table('jobs')->where('queue', 'question-papers')->count(), 'no question paper job is left waiting');
        $left = DB::table('jobs')->where('id', $sentinel)->first();
        $this->assertNotNull($left, 'the default-queue job was not consumed');
        $this->assertSame([0, null], [(int) $left->attempts, $left->reserved_at], 'the default-queue job was not even reserved');
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_a_worker_drains_multiple_papers_queued_before_it_starts(): void
    {
        $this->fixture($this->questions('Batch'));
        $uploads = [
            $this->uploadPaper('batch-one.pdf'),
            $this->uploadPaper('batch-two.pdf'),
            $this->uploadPaper('batch-three.pdf'),
        ];
        $this->assertSame(3, DB::table('jobs')->where('queue', 'question-papers')->count());

        $worker = $this->startWorker();
        $this->waitFor(
            fn () => DB::table('question_paper_jobs')->whereIn('uuid', array_column($uploads, 'id'))
                ->whereIn('status', ['queued', 'processing', 'retrying'])->doesntExist(),
            90,
            'the worker did not drain all queued uploads'
        );

        $this->assertSame(3, DB::table('question_paper_jobs')->whereIn('uuid', array_column($uploads, 'id'))->count());
        $this->assertSame(3, DB::table('lms_question_master')->count(), 'the same question bank items are not duplicated by later papers');
        $this->assertSame(3, DB::table('user_notifications')->count(), 'every upload receives its own outcome notice');
        $this->waitFor(fn () => DB::table('jobs')->where('queue', 'question-papers')->doesntExist(), 10, 'the queue rows were not deleted after processing');
        $this->assertSame(0, DB::table('jobs')->where('queue', 'question-papers')->count());
        $this->assertTrue(proc_get_status($worker['process'])['running']);
    }

    public function test_the_screen_can_tell_a_missing_worker_from_a_running_one(): void
    {
        $this->fixture($this->questions('Heartbeat'));
        $jobs = app(\App\Services\QuestionPapers\QuestionPaperJobService::class);

        // Queued, and no worker process exists yet: the honest answer is "not detected", not "processing".
        $waiting = $this->makeJob(academic: ['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101]);
        $this->assertSame('queued', $jobs->present($this->jobRow($waiting))['status']);
        $this->assertSame('not_detected', $jobs->present($this->jobRow($waiting))['worker_state']);

        // A real worker starts and begins polling its queue: it reports itself alive within seconds.
        $worker = $this->startWorker();
        $this->waitFor(
            fn () => in_array(\App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat::state(), ['active', 'busy'], true),
            30,
            'the running worker never reported itself alive'
        );
        $this->assertTrue(proc_get_status($worker['process'])['running']);

        // When it stops, the heartbeat goes stale and the screen says so again (aged by the clock, not by waiting).
        $this->stopProcess($worker['process']);
        $this->workers = [];
        $this->assertSame('not_detected', \App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat::state(time() + 120));
    }

    public function test_processing_continues_with_no_browser_request_or_session_alive(): void
    {
        $this->fixture($this->questions('Closed'));
        $this->startWorker();

        $data = $this->uploadPaper();
        // The "browser" is gone from here on: no request object, no poll, no further API call. Only the worker acts.
        $uuid = $data['id'];
        $this->waitFor(fn () => $this->jobIsFinished($uuid), 60, 'processing did not continue without the browser');

        $row = DB::table('question_paper_jobs')->where('uuid', $uuid)->first();
        $this->assertSame('completed', $row->status);
        $this->assertSame(3, DB::table('lms_question_master')->count());
        // What the person sees when they come back is read from storage, not from anything the page kept.
        $this->assertSame(self::OWNER, (int) DB::table('user_notifications')->value('user_id'));
    }

    public function test_a_worker_restart_and_a_redispatch_cannot_duplicate_questions_or_notifications(): void
    {
        $this->fixture($this->questions('Restart'));
        $first = $this->startWorker();
        $upload = $this->uploadPaper();
        $this->waitFor(fn () => $this->jobIsFinished($upload['id']), 60, 'upload was not processed');
        $questions = DB::table('lms_question_master')->count();
        $answers = DB::table('answer_master')->count();

        // The worker dies (deploy, crash, reboot) ...
        $this->stopProcess($first['process']);
        $this->workers = [];

        // ... and the same job is delivered again, as a lost-message recovery or duplicate delivery would.
        $job = DB::table('question_paper_jobs')->where('uuid', $upload['id'])->first();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'queued', 'finished_at' => null, 'parsed_chunks' => 0, 'total_chunks' => null, 'last_dispatched_at' => null]);
        $replacement = $this->startWorker();
        $this->assertNotSame($first['pid'], $replacement['pid']);
        app(\App\Services\QuestionPapers\QuestionPaperJobService::class)->dispatch(DB::table('question_paper_jobs')->where('id', $job->id)->first());

        $this->waitFor(fn () => $this->jobIsFinished($upload['id']) && DB::table('jobs')->where('queue', 'question-papers')->count() === 0, 60, 'the replacement worker did not finish the redelivered job');

        $this->assertSame($questions, DB::table('lms_question_master')->count(), 'no duplicate questions');
        $this->assertSame($answers, DB::table('answer_master')->count(), 'no duplicate answer rows');
        $this->assertSame(3, DB::table('question_paper_items')->where('job_id', $job->id)->count());
        $this->assertSame(1, DB::table('user_notifications')->count(), 'no second completion notice for the same outcome');
    }

    public function test_the_scheduler_recovers_a_lost_queue_message_and_the_running_worker_finishes_it(): void
    {
        $this->fixture($this->questions('Recovered'));
        $this->startWorker();

        // A job whose queue message was lost: row exists, was never dispatched, nothing in the jobs table.
        $job = $this->makeJob(academic: ['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101]);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('queued', $this->jobRow($job)->status);

        $this->artisan('idms:dispatch-question-papers')->expectsOutput('Dispatched 1 question paper job(s).')->assertExitCode(0);

        $this->waitFor(fn () => $this->jobIsFinished($job->uuid), 60, 'the recovered job was not finished by the worker');
        $this->assertSame('completed', $this->jobRow($job)->status);
        $this->assertSame(3, DB::table('lms_question_master')->count());

        // A finished job is never dispatched again.
        $this->artisan('idms:dispatch-question-papers')->expectsOutput('Dispatched 0 question paper job(s).')->assertExitCode(0);
        $this->waitFor(fn () => DB::table('jobs')->doesntExist(), 10, 'the completed queue message was not removed');
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_a_scheduled_upload_waits_until_due_then_dispatches_to_the_worker_automatically(): void
    {
        $this->fixture($this->questions('Scheduled'));
        $future = now()->addHour()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $upload = $this->uploadPaper('scheduled-paper.pdf', $future);
        $this->assertSame('queued', $upload['status']);
        $this->assertNotNull($upload['scheduled_at']);
        $this->assertSame(0, DB::table('jobs')->count(), 'future uploads are not put on the extraction queue early');

        $worker = $this->startWorker();
        $this->artisan('idms:dispatch-question-papers')->expectsOutput('Dispatched 0 question paper job(s).')->assertExitCode(0);
        $this->assertSame(0, DB::table('lms_question_master')->count());

        DB::table('question_paper_jobs')->where('uuid', $upload['id'])->update(['scheduled_at' => now('UTC')->subSecond()]);
        $this->artisan('idms:dispatch-question-papers')->expectsOutput('Dispatched 1 question paper job(s).')->assertExitCode(0);
        $this->waitFor(fn () => $this->jobIsFinished($upload['id']), 60, 'the worker did not process the due scheduled upload');

        $job = DB::table('question_paper_jobs')->where('uuid', $upload['id'])->first();
        $this->assertSame('completed', $job->status);
        $this->assertSame([3, 3], [(int) $job->questions_extracted, (int) $job->questions_saved]);
        $this->assertSame(3, DB::table('lms_question_master')->count());
        $this->assertSame(8, DB::table('answer_master')->count());
        $this->assertTrue(proc_get_status($worker['process'])['running']);
    }

    public function test_every_shipped_worker_definition_runs_only_the_question_papers_queue(): void
    {
        $required = ['queue:work', 'question_papers', '--queue=question-papers', '--timeout=900'];
        $files = [
            'deploy/question-papers/supervisor-question-papers.conf',
            'deploy/question-papers/question-papers-worker.service',
            'dev-question-papers.ps1',
        ];

        foreach ($files as $file) {
            $text = (string) file_get_contents(base_path($file));
            $this->assertNotSame('', $text, "{$file} is missing");
            foreach ($required as $needle) {
                $this->assertStringContainsString($needle, $text, "{$file} must contain {$needle}");
            }
            $this->assertDoesNotMatchRegularExpression('/queue:work\s+(?!question_papers)\S*/', $text, "{$file} starts a worker for another connection");
            $this->assertStringNotContainsString('--queue=default', $text, $file);
            $code = preg_replace(['/<#.*?#>/s', '/^\s*[#;].*$/m'], '', $text); // comments may mention it; code may not run it
            $this->assertStringNotContainsString('schedule:work', $code, "{$file} must not start the full scheduler (it would run every unrelated task)");
        }

        // The worker timeout must stay below the queue's redelivery window.
        $this->assertGreaterThan(900, (int) config('queue.connections.question_papers.retry_after'));
    }

    private function jobRow(object $job): object
    {
        return DB::table('question_paper_jobs')->where('id', $job->id)->first();
    }
}
