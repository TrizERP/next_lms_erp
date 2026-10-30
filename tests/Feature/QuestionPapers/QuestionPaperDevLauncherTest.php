<?php

namespace Tests\Feature\QuestionPapers;

use App\Jobs\QuestionPaperExtractionJob;
use App\Services\QuestionPapers\QuestionPaperJobService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The local development launcher (dev-question-papers.ps1), exercised as a REAL process tree on a scratch database.
 *
 * It proves the things a person relies on when they type one command: both processes start, the worker listens on the
 * question-papers queue only, a new job is picked up with nobody running anything, a lost queue message is recovered by
 * the launcher's dispatcher, a crashed worker is replaced, a second copy is refused, nothing on the default queue is
 * consumed, and stopping leaves no process behind.
 *
 * SAFETY. The server, worker and dispatcher run against a qp-dev-*.sqlite file in the temp directory: the test refuses to
 * start the launcher unless a probe boots the application with the same overrides and reports exactly that file. The
 * synthetic jobs point at a document that does not exist, so they finish at once with a permanent failure and touch no AI
 * provider, no Spaces storage and no shared table. Windows only (the launcher is PowerShell).
 */
class QuestionPaperDevLauncherTest extends QuestionPaperTestCase
{
    private string $scratch = '';
    private ?string $dbFile = null;
    private int $port = 0;
    private ?string $pidFile = null;
    /** @var resource|null */
    private $launcher = null;
    /** @var list<string> dev logs that did not exist before this test, to be removed afterwards */
    private array $logsBefore = [];

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The launcher is a PowerShell script (Windows only).');
        }
        if (getenv('QP_MYSQL_PORT')) {
            $this->markTestSkipped('scratch sqlite file only');
        }
        parent::setUp();

        $this->pidFile = base_path('storage/framework/dev-question-papers.json');
        if (is_file($this->pidFile) || $this->workers() !== []) {
            $this->markTestSkipped('A question-papers worker or launcher is already running for this checkout; this test would stop it.');
        }
        $this->logsBefore = glob(base_path('storage/logs/dev-*.log')) ?: [];
    }

    protected function tearDown(): void
    {
        if ($this->scratch !== '') {
            $this->stopLauncher(); // never leave a process behind, whatever failed
            if (is_resource($this->launcher)) {
                @proc_terminate($this->launcher);
                @proc_close($this->launcher);
            }
            foreach (array_diff(glob(base_path('storage/logs/dev-*.log')) ?: [], $this->logsBefore) as $log) {
                @unlink($log);
            }
        }
        parent::tearDown();
        if ($this->scratch !== '' && is_dir($this->scratch)) {
            foreach (glob($this->scratch . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->scratch);
        }
    }

    protected function sqliteDatabase(): string
    {
        if ($this->dbFile === null) {
            $this->scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qp-dev-' . bin2hex(random_bytes(5));
            mkdir($this->scratch);
            $this->dbFile = $this->scratch . DIRECTORY_SEPARATOR . 'qp-dev-' . bin2hex(random_bytes(4)) . '.sqlite';
            touch($this->dbFile);
        }

        return $this->dbFile;
    }

    protected function createSurroundingTables(): void
    {
        DB::select('PRAGMA journal_mode=WAL');
        parent::createSurroundingTables();
        (require base_path('database/migrations/2026_08_20_000001_create_jobs_table.php'))->up();
        (require base_path('database/migrations/2019_08_19_000000_create_failed_jobs_table.php'))->up();
        Schema::create('custom_module_tables', function ($t) {
            $t->increments('id');
            $t->string('module_name')->nullable();
            $t->string('access_link')->nullable();
        });
    }

    /* ---------- helpers ---------- */

    /** Overrides that win over .env in every process the launcher starts. */
    private function launcherEnv(): array
    {
        return array_merge(getenv() ?: [], [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->dbFile, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
            'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'database', 'LOG_CHANNEL' => 'stderr',
            'GEMINI_API_KEY' => '', 'OPENAI_API_KEY' => '', 'DEEPSEEK_API_KEY' => '', 'ANTHROPIC_API_KEY' => '', 'OPENROUTER_API_KEY' => '',
        ]);
    }

    /** @return array{0: int, 1: string} exit code and combined output */
    private function exec(array $command): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->launcherEnv());
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out];
    }

    private function launcherCommand(string ...$extra): array
    {
        return array_merge(['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', base_path('dev-question-papers.ps1')], $extra);
    }

    /** @return array<int, string> pid => command line, for php processes of THIS checkout (absolute artisan path) running queue:work */
    private function workers(): array
    {
        return array_filter($this->phpProcesses(), fn ($cmd) => str_contains($cmd, 'queue:work'));
    }

    /** @return array<int, string> */
    private function phpProcesses(): array
    {
        $csv = (string) shell_exec('wmic process where "name=\'php.exe\'" get CommandLine,ProcessId /format:csv 2>NUL');
        $artisan = strtolower(base_path('artisan'));
        $found = [];
        foreach (preg_split('/\r?\n/', $csv) ?: [] as $line) {
            if (preg_match('/^[^,]*,(.*),(\d+)\s*$/', $line, $m) && str_contains(strtolower($m[1]), $artisan)) {
                $found[(int) $m[2]] = $m[1];
            }
        }

        return $found;
    }

    private function portOpen(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.5);
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    private function waitFor(callable $condition, int $seconds, string $failure): void
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            if ($condition()) {
                return;
            }
            usleep(400_000);
        }
        $this->fail($failure);
    }

    private function stopLauncher(): void
    {
        if ($this->dbFile !== null && is_file(base_path('dev-question-papers.ps1'))) {
            $this->exec($this->launcherCommand('-Stop'));
        }
    }

    /** A job whose document does not exist: it ends in a permanent failure immediately, with no AI and no storage access. */
    private function syntheticJob(): object
    {
        return app(QuestionPaperJobService::class)->create(900000 + random_int(1, 90000), self::TENANT, self::OWNER);
    }

    private function processed(object $job): bool
    {
        $row = DB::table('question_paper_jobs')->where('id', $job->id)->first();

        return $row !== null && $row->status === 'failed' && str_contains((string) $row->last_error, 'no longer exists');
    }

    /* ---------- the test ---------- */

    public function test_one_command_starts_the_server_and_a_question_papers_only_worker_and_keeps_processing_on_its_own(): void
    {
        // A free port for the launcher's own server.
        $probeSocket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) substr(strrchr(stream_socket_get_name($probeSocket, false), ':'), 1);
        fclose($probeSocket);

        // SAFETY: the processes the launcher starts must resolve to the scratch database. Refuse otherwise.
        [$code, $out] = $this->exec([PHP_BINARY, base_path('tests/Support/dev_launcher_probe.php'), $this->dbFile]);
        $this->assertSame(0, $code, "probe failed: {$out}");
        $probe = json_decode(trim((string) strrchr("\n" . trim($out), "\n")), true);
        $this->assertSame(str_replace('\\', '/', $this->dbFile), $probe['database'], 'the launcher would NOT be on the scratch database; refusing to start it');
        $this->assertSame(['sqlite', 'database', 'question-papers'], [$probe['driver'], $probe['queue_default'], $probe['question_papers_queue']]);

        // Seed: a default-queue job that must never be touched, and a job whose queue message was "lost" (never dispatched).
        $sentinel = (int) DB::table('jobs')->insertGetId([
            'queue' => 'default', 'payload' => json_encode(['displayName' => 'Sentinel', 'job' => 'Sentinel', 'data' => []]),
            'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
        ]);
        $lost = $this->syntheticJob();
        $this->assertSame(0, DB::table('jobs')->where('queue', 'question-papers')->count(), 'no queue message exists for the first job');

        // THE command: server + worker + recovery dispatcher, started once.
        $log = $this->scratch . DIRECTORY_SEPARATOR . 'launcher.log';
        $this->launcher = proc_open($this->launcherCommand('-Port', (string) $this->port), [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->launcherEnv());
        $this->assertIsResource($this->launcher);

        // 1. Both processes come up.
        $this->waitFor(fn () => count($this->workers()) === 1 && $this->portOpen($this->port), 60, 'the launcher did not start a worker and a server: ' . (is_file($log) ? file_get_contents($log) : ''));
        $worker = array_key_first($this->workers());
        $command = $this->workers()[$worker];
        $this->assertStringContainsString('queue:work question_papers', $command);
        $this->assertStringContainsString('--queue=question-papers', $command);
        $this->assertStringContainsString('--timeout=900', $command);
        $this->assertStringNotContainsString('--queue=default', $command);
        $this->assertTrue(is_file($this->pidFile), 'the supervisor records itself so a second copy can be refused');

        // 2. The lost message is recovered by the dispatcher and the worker finishes the job: nobody ran a command.
        $this->waitFor(fn () => $this->processed($lost), 90, 'the recovered job was not processed automatically: ' . (is_file($log) ? file_get_contents($log) : ''));

        // 3. A second copy is refused and does not add a worker.
        [$dupCode, $dupOut] = $this->exec($this->launcherCommand('-Port', (string) $this->port));
        $this->assertNotSame(0, $dupCode);
        $this->assertStringContainsString('Already running', $dupOut);
        $this->assertCount(1, $this->workers(), 'no duplicate worker');

        // 4. A new job, dispatched normally (as an upload would), is picked up by the running worker.
        $fresh = $this->syntheticJob();
        $this->assertTrue(app(QuestionPaperJobService::class)->dispatch($fresh));
        $this->waitFor(fn () => $this->processed($fresh), 45, 'a newly queued job was not picked up automatically');
        $this->assertSame($worker, array_key_first($this->workers()), 'by the same worker process');

        // 5. The worker crashes: the supervisor replaces it, and the replacement processes the next job.
        $this->exec(['taskkill', '/F', '/T', '/PID', (string) $worker]);
        $this->waitFor(fn () => ($now = array_key_first($this->workers())) !== null && $now !== $worker, 60, 'the crashed worker was not replaced');
        $afterCrash = $this->syntheticJob();
        $this->assertTrue(app(QuestionPaperJobService::class)->dispatch($afterCrash));
        $this->waitFor(fn () => $this->processed($afterCrash), 45, 'the replacement worker did not process the next job');
        $this->assertCount(1, $this->workers());

        // 6. Only this feature's queue was consumed; no failures were recorded by the queue itself.
        $left = DB::table('jobs')->where('id', $sentinel)->first();
        $this->assertNotNull($left);
        $this->assertSame([0, null], [(int) $left->attempts, $left->reserved_at], 'the default-queue job was not touched');
        $this->assertSame(0, DB::table('jobs')->where('queue', 'question-papers')->count(), 'nothing is left waiting on the question-papers queue');
        $this->assertSame(3, DB::table('question_paper_jobs')->where('status', 'failed')->count(), 'all three synthetic jobs were processed, and only those');
        $this->assertSame(3, DB::table('user_notifications')->count(), 'one outcome notice per processed job');

        // 7. Stopping ends everything it started and clears the record.
        $before = is_file($this->pidFile) ? (string) file_get_contents($this->pidFile) : '(none)';
        [, $stopOutput] = $this->exec($this->launcherCommand('-Stop'));
        $deadline = microtime(true) + 30;
        clearstatcache(true); // another process deletes the file; PHP must not answer from its stat cache
        while (microtime(true) < $deadline && ($this->workers() !== [] || $this->portOpen($this->port) || is_file($this->pidFile))) {
            usleep(400_000);
            clearstatcache(true);
        }
        $this->assertSame([], $this->workers(), 'a worker survived -Stop');
        $this->assertFalse($this->portOpen($this->port), 'the server survived -Stop');
        $this->assertFalse(is_file($this->pidFile), 'the pid file survived -Stop. before: ' . $before . ' | after: ' . (string) @file_get_contents($this->pidFile) . ' | stop said: ' . trim($stopOutput));
        $this->assertSame([], array_filter($this->phpProcesses(), fn ($c) => str_contains($c, 'serve')), 'an artisan serve process survived -Stop');
    }
}
