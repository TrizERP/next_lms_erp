<?php

namespace Tests\Feature\QuestionPapers;

use App\Services\QuestionPapers\Worker\LauncherDriver;
use App\Services\QuestionPapers\Worker\QuestionPaperWorkerManager;
use App\Services\QuestionPapers\Worker\SymfonyCommandRunner;
use App\Services\QuestionPapers\Worker\SystemProcessTable;
use App\Services\QuestionPapers\Worker\WorkerLock;
use App\Services\QuestionPapers\Worker\WorkerSignature;
use App\Services\QuestionPapers\Worker\WorkerStateStore;

/**
 * REAL process control, isolated: the launcher starts the genuine `php artisan queue:work question_papers
 * --queue=question-papers` command, but against a SCRATCH sqlite database (empty `jobs` table) and a scratch state folder, so
 * it can never see the shared database, an existing Question Paper job, or the `default` queue. Nothing is extracted.
 *
 * A decoy process whose command line is a `default`-queue worker proves the control never touches other workers.
 * Skipped if a real worker for this checkout is already running (the developer's own), because the control would see it.
 */
class QuestionPaperWorkerProcessControlTest extends QuestionPaperTestCase
{
    private string $dir = '';
    private string $db = '';
    /** @var resource|null */
    private $decoy = null;
    private ?QuestionPaperWorkerManager $manager = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('QP_MYSQL_PORT')) {
            $this->markTestSkipped('real-process tests run on the scratch sqlite file only');
        }

        if ($this->realWorkers() !== []) {
            $this->markTestSkipped('a real question-papers worker for this checkout is already running; not touching it');
        }

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qp-proc-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->db = $this->dir . DIRECTORY_SEPARATOR . 'scratch.sqlite';
        $pdo = new \PDO('sqlite:' . $this->db);
        $pdo->exec('create table jobs (id integer primary key autoincrement, queue varchar not null, payload text not null, attempts integer not null, reserved_at integer, available_at integer not null, created_at integer not null)');
        $pdo->exec('create table failed_jobs (id integer primary key autoincrement, uuid varchar, connection text, queue text, payload text, exception text, failed_at datetime)');
        // routes/custom_module.php reads this table while the application boots; empty is enough.
        $pdo->exec('create table custom_module_tables (id integer primary key autoincrement, module_name varchar, access_link varchar)');
        $pdo = null;

        config([
            'idms.question_paper.worker.state_dir' => $this->dir,
            'idms.question_paper.worker.php_cli' => PHP_BINARY,
        ]);

        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->db, 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
            'DB_USERNAME' => 'none', 'DB_PASSWORD' => 'none', 'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1', 'CACHE_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'database', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'stderr',
            'QUESTION_PAPER_WORKER_STATE_DIR' => $this->dir, 'QUESTION_PAPER_HEARTBEAT_TRUSTED' => 'false',
            'GEMINI_API_KEY' => '', 'OPENAI_API_KEY' => '', 'OPENROUTER_API_KEY' => '', 'DEEPSEEK_API_KEY' => '', 'ANTHROPIC_API_KEY' => '',
        ];
        $store = new WorkerStateStore($this->dir);
        $driver = new LauncherDriver(new SystemProcessTable(), $store, new SymfonyCommandRunner(), $env);
        $this->manager = new QuestionPaperWorkerManager($driver, $store, new WorkerLock($this->dir), 40, 25);

        // A `default`-queue worker look-alike: same artisan, a different queue. It must be invisible to the control.
        $this->decoy = proc_open(
            [PHP_BINARY, '-r', 'sleep(240);', '--', base_path('artisan'), 'queue:work', 'database', '--queue=default'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'question-papers-worker.stop', 'test cleanup');
            $this->waitFor(fn () => $this->realWorkers() === [], 15);
            $this->stopDevSupervisor();
            foreach ($this->realWorkers() as $pid) {
                $this->kill($pid);
            }
        }
        if (is_resource($this->decoy)) {
            $decoy = proc_get_status($this->decoy);
            if ($decoy['running']) {
                $this->kill((int) $decoy['pid']);
            }
            proc_terminate($this->decoy);
            proc_close($this->decoy);
        }
        if ($this->dir !== '' && is_dir($this->dir)) {
            usleep(500000);
            $logs = $this->dir . DIRECTORY_SEPARATOR . 'logs';
            foreach (glob($logs . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            if (is_dir($logs)) {
                @rmdir($logs);
            }
            foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function stopDevSupervisor(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !is_file($this->dir . DIRECTORY_SEPARATOR . 'dev-question-papers.json')) {
            return;
        }

        $process = proc_open(
            ['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', base_path('dev-question-papers.ps1'), '-Stop', '-StateDirectory', $this->dir],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            ['QUESTION_PAPER_WORKER_STATE_DIR' => $this->dir]
        );
        if (is_resource($process)) {
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    /** @return list<int> */
    private function realWorkers(): array
    {
        $pids = [];
        foreach ((new SystemProcessTable())->php() as $process) {
            if (WorkerSignature::matches($process['command'])) {
                $pids[] = $process['pid'];
            }
        }

        return $pids;
    }

    private function kill(int $pid): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('taskkill /F /PID ' . $pid . ' 2>NUL');
        } elseif (function_exists('posix_kill')) {
            posix_kill($pid, 9);
        }
    }

    /** @return array<string, string|false> */
    private function logs(): array
    {
        $logs = [];
        foreach (array_merge(
            glob($this->dir . DIRECTORY_SEPARATOR . '*.log') ?: [],
            glob($this->dir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '*.log') ?: [],
        ) as $file) {
            $logs[basename($file)] = file_get_contents($file);
        }

        return $logs;
    }

    private function waitFor(callable $condition, int $seconds): bool
    {
        $until = microtime(true) + $seconds;
        do {
            if ($condition()) {
                return true;
            }
            usleep(300000);
        } while (microtime(true) < $until);

        return false;
    }

    public function test_real_start_duplicate_refusal_graceful_stop_no_respawn_restart_and_crash_recovery(): void
    {
        $manager = $this->manager;
        $this->assertSame('stopped', $manager->status()['state']);
        $this->assertSame(0, $manager->status()['active_workers'], 'the decoy default worker is not counted');

        // START: a real process, verified before "running" is reported.
        $started = $manager->start('process test');
        $logs = $this->logs();
        $this->assertSame(200, $started['http'], json_encode($started['status']) . ' logs=' . json_encode($logs));
        $this->assertSame(['running', 1], [$started['status']['state'], $started['status']['active_workers']]);
        $this->assertCount(1, $this->realWorkers());

        // DUPLICATE START: refused, still exactly one worker.
        $this->assertSame(409, $manager->start()['http']);
        $this->assertCount(1, $this->realWorkers());

        // GRACEFUL STOP: the worker exits by itself (no kill), only this worker is gone, the decoy lives on.
        $stopped = $manager->stop('process test');
        $this->assertContains($stopped['http'], [200, 202]);
        $this->assertTrue($this->waitFor(fn () => $this->realWorkers() === [], 25), 'the worker did not exit after the stop flag');
        $stoppedStatus = $manager->status();
        $this->assertSame('stopped', $stoppedStatus['state'], json_encode($stoppedStatus));
        $this->assertTrue(is_resource($this->decoy) && proc_get_status($this->decoy)['running'], 'the default-queue look-alike was untouched');

        // NO RESPAWN while stopped: polling with reconcile must not bring it back.
        for ($i = 0; $i < 3; $i++) {
            $manager->status(reconcile: true);
            usleep(500000);
        }
        $this->assertSame([], $this->realWorkers(), 'a manual stop overrides automatic starting');

        // START AGAIN works and lifts the stop.
        $again = $manager->start();
        $this->assertSame(200, $again['http'], json_encode($again['status']));
        $this->assertCount(1, $this->realWorkers());

        // CRASH: kill it hard. The next status poll (past the start grace) restarts it once, within the budget.
        $this->kill($this->realWorkers()[0]);
        $this->assertTrue($this->waitFor(fn () => $this->realWorkers() === [], 10));
        (new WorkerStateStore($this->dir))->update(['last_action' => ['action' => 'start', 'at' => time() - 600, 'by' => null]]);
        $manager->status(reconcile: true);
        $this->assertTrue($this->waitFor(fn () => count($this->realWorkers()) === 1, 40), 'the crashed worker was not restarted');
        $this->assertCount(1, $this->realWorkers(), 'exactly one, never two');

        // And a final stop leaves nothing behind.
        $manager->stop();
        $this->assertTrue($this->waitFor(fn () => $this->realWorkers() === [], 25));
    }

    public function test_a_stale_state_record_cannot_make_a_dead_worker_look_alive_and_a_stale_flag_cannot_block_a_start(): void
    {
        $store = new WorkerStateStore($this->dir);
        $store->update(['desired' => 'running', 'last_action' => ['action' => 'start', 'at' => time() - 3600, 'by' => 'old']]);
        $store->requestStop(); // left over from some earlier stop

        $status = $this->manager->status();
        $this->assertSame(0, $status['active_workers']);
        $this->assertNotSame('running', $status['state']);

        $started = $this->manager->start();
        $this->assertSame(200, $started['http'], json_encode($started['status']) . ' logs=' . json_encode($this->logs()));
        $this->assertFalse($store->stopRequested(), 'the old flag was lifted, so the new worker did not quit at once');
        $this->assertCount(1, $this->realWorkers());

        $this->manager->stop();
        $this->assertTrue($this->waitFor(fn () => $this->realWorkers() === [], 25));
    }
}
