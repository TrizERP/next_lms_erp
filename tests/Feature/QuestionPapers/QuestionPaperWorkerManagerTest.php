<?php

namespace Tests\Feature\QuestionPapers;

require_once __DIR__ . '/../../Support/WorkerFakes.php';

use App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat;
use App\Services\QuestionPapers\Worker\CommandRunner;
use App\Services\QuestionPapers\Worker\LauncherDriver;
use App\Services\QuestionPapers\Worker\ProcessTable;
use App\Services\QuestionPapers\Worker\QuestionPaperWorkerManager;
use App\Services\QuestionPapers\Worker\SupervisorDriver;
use App\Services\QuestionPapers\Worker\SystemdDriver;
use App\Services\QuestionPapers\Worker\UnavailableDriver;
use App\Services\QuestionPapers\Worker\WorkerBusy;
use App\Services\QuestionPapers\Worker\WorkerDriver;
use App\Services\QuestionPapers\Worker\WorkerLock;
use App\Services\QuestionPapers\Worker\WorkerSignature;
use App\Services\QuestionPapers\Worker\WorkerStateStore;
use Illuminate\Support\Facades\DB;

/**
 * The worker control panel's brain, tested without starting any real process: the allowlist, the three drivers, and the
 * state machine (verified states, no duplicate, graceful stop, restart policy, stale state, locking).
 */
class QuestionPaperWorkerManagerTest extends QuestionPaperTestCase
{
    private string $stateDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qp-state-' . bin2hex(random_bytes(4));
        mkdir($this->stateDir);
        config(['idms.question_paper.worker.state_dir' => $this->stateDir]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->stateDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->stateDir);
        parent::tearDown();
    }

    private function artisanPath(): string
    {
        return base_path('artisan');
    }

    private function workerLine(string $queue = 'question-papers', string $connection = 'question_papers'): string
    {
        return '"C:\\xampp\\php\\php.exe" "' . $this->artisanPath() . '" queue:work ' . $connection . ' --queue=' . $queue . ' --sleep=3 --tries=6 --timeout=900';
    }

    private function manager(WorkerDriver $driver, int $start = 0, int $stop = 0): QuestionPaperWorkerManager
    {
        return new QuestionPaperWorkerManager($driver, new WorkerStateStore($this->stateDir), new WorkerLock($this->stateDir), $start, $stop);
    }

    private function store(): WorkerStateStore
    {
        return new WorkerStateStore($this->stateDir);
    }

    /* ---------- the allowlist ---------- */

    public function test_only_this_checkouts_question_papers_worker_is_recognised(): void
    {
        $this->assertTrue(WorkerSignature::matches($this->workerLine()));
        $this->assertTrue(WorkerSignature::matches(strtolower(str_replace('\\', '/', $this->workerLine())) . ' --max-time=3600'), 'path style and case do not matter');

        foreach ([
            'default queue worker' => '"php" "' . $this->artisanPath() . '" queue:work database --queue=default --sleep=3',
            'bare queue:work' => '"php" "' . $this->artisanPath() . '" queue:work',
            'another connection' => $this->workerLine('question-papers', 'database'),
            'queue name that only starts the same' => $this->workerLine('question-papers-extra'),
            'two queues' => $this->workerLine('question-papers,default'),
            'a listener' => '"php" "' . $this->artisanPath() . '" queue:listen question_papers --queue=question-papers',
            'a shell supervisor command that contains a worker argv' => 'sh -c "while true; do php "' . $this->artisanPath() . '" queue:work question_papers --queue=question-papers; done"',
            'another checkout' => '"php" "C:\\other\\site\\artisan" queue:work question_papers --queue=question-papers',
            'the web server' => '"C:\\xampp\\php\\php.exe" artisan serve',
        ] as $label => $line) {
            $this->assertFalse(WorkerSignature::matches($line), $label);
        }
    }

    public function test_the_worker_command_is_fixed_and_the_queue_name_is_validated(): void
    {
        $this->assertSame(
            ['php', $this->artisanPath(), 'queue:work', 'question_papers', '--queue=question-papers', '--sleep=3', '--tries=6', '--timeout=900', '--max-time=3600'],
            WorkerSignature::command('php')
        );
        $this->assertNotContains('--max-time=3600', WorkerSignature::command('php', false));

        config(['queue.connections.question_papers.queue' => 'x; rm -rf /']);
        $this->assertSame('question-papers', WorkerSignature::queue(), 'a queue name that is not a plain name never reaches a command');
        $this->assertNotNull(WorkerSignature::configurationError(), 'a misconfigured queue makes worker control unavailable');
        $this->assertContains('--queue=question-papers', WorkerSignature::command('php'));
    }

    /* ---------- launcher driver ---------- */

    private function launcher(FakeProcessTable $table, FakeRunner $runner): LauncherDriver
    {
        config(['idms.question_paper.worker.php_cli' => PHP_BINARY]);

        return new LauncherDriver($table, $this->store(), $runner);
    }

    public function test_the_launcher_counts_only_the_allowlisted_worker_and_ignores_a_default_queue_worker(): void
    {
        $table = new FakeProcessTable([
            101 => $this->workerLine(),
            102 => '"php" "' . $this->artisanPath() . '" queue:work database --queue=default',
            103 => '"php" "' . $this->artisanPath() . '" serve',
        ]);

        $this->assertSame(1, $this->launcher($table, new FakeRunner())->inspect()['workers']);
    }

    public function test_the_launcher_starts_exactly_one_allowlisted_command_and_clears_an_old_stop(): void
    {
        $runner = new FakeRunner();
        $store = $this->store();
        $store->requestStop();
        $this->assertTrue($store->stopRequested());

        $this->launcher(new FakeProcessTable([]), $runner)->start();

        $this->assertFalse($store->stopRequested(), 'a stop left by an earlier Stop would make the new worker quit at once');
        $this->assertCount(1, $runner->calls);
        $spawn = implode(' ', $runner->calls[0]);
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertSame(1, preg_match('/-EncodedCommand ([A-Za-z0-9+\/=]+)/', $spawn, $match));
            $encoded = base64_decode($match[1], true);
            $this->assertIsString($encoded);
            $command = iconv('UTF-16LE', 'UTF-8', $encoded);
            $this->assertIsString($command);
            $this->assertStringContainsString('dev-question-papers.ps1', $command);
            $this->assertStringContainsString('-NoServer', $command);
            $this->assertStringContainsString('-StateDirectory', $command);
        } else {
            $this->assertStringContainsString('queue:work', $spawn);
            $this->assertStringContainsString('question_papers', $spawn);
            $this->assertStringContainsString('--queue=question-papers', $spawn);
            $this->assertStringNotContainsString('default', $spawn);
            $this->assertStringNotContainsString('--max-time', $spawn, 'the local launcher leaves the worker available for its own restart policy');
        }
    }

    public function test_the_launcher_does_not_start_a_second_worker_beside_the_dev_launchers_supervisor(): void
    {
        file_put_contents($this->stateDir . DIRECTORY_SEPARATOR . 'dev-question-papers.json', json_encode(['supervisor' => 4242]));
        $store = $this->store();
        $store->requestStop();

        $table = new FakeProcessTable([]);
        $table->commands[4242] = 'powershell.exe -File C:\\x\\dev-question-papers.ps1 -NoServer';
        $runner = new FakeRunner();
        $this->launcher($table, $runner)->start();

        $this->assertSame([], $runner->calls, 'its supervisor brings its own worker back');
        $this->assertFalse($store->stopRequested(), 'but the stop is lifted so it can');
    }

    public function test_a_stale_dev_launcher_pid_that_now_belongs_to_another_program_is_ignored(): void
    {
        file_put_contents($this->stateDir . DIRECTORY_SEPARATOR . 'dev-question-papers.json', json_encode(['supervisor' => 4242]));
        $table = new FakeProcessTable([]);
        $table->commands[4242] = 'C:\\Windows\\notepad.exe'; // the pid was recycled
        $runner = new FakeRunner();

        $this->launcher($table, $runner)->start();

        $this->assertCount(1, $runner->calls, 'a worker is started; the dead pid file did not block it');

        // And a pid that no longer exists at all.
        $runner2 = new FakeRunner();
        $this->launcher(new FakeProcessTable([]), $runner2)->start();
        $this->assertCount(1, $runner2->calls);
    }

    public function test_the_launcher_stops_gracefully_with_a_flag_and_never_runs_a_command(): void
    {
        $runner = new FakeRunner();

        $this->launcher(new FakeProcessTable([101 => $this->workerLine()]), $runner)->stop();

        $this->assertTrue($this->store()->stopRequested());
        $this->assertSame([], $runner->calls, 'nothing is killed');
    }

    /* ---------- service drivers ---------- */

    public function test_supervisor_runs_only_its_fixed_commands_and_reads_the_real_status(): void
    {
        $runner = new FakeRunner(['status' => "question-papers-worker:question-papers-worker_00   RUNNING   pid 123, uptime 0:01:00\n"]);
        $driver = new SupervisorDriver($runner, 'question-papers-worker', false);

        $this->assertSame(['workers' => 1, 'transitional' => null, 'detail' => null], $driver->inspect());
        $driver->start();
        $driver->stop();

        $this->assertSame([
            ['supervisorctl', 'status', 'question-papers-worker:*'],
            ['supervisorctl', 'start', 'question-papers-worker:*'],
            ['supervisorctl', 'stop', 'question-papers-worker:*'],
        ], $runner->calls);
    }

    public function test_supervisor_with_sudo_prefixes_only_the_exact_command_and_reports_stopping_and_fatal(): void
    {
        $runner = new FakeRunner(['status' => "question-papers-worker:question-papers-worker_00   STOPPING\n"]);
        $this->assertSame('stopping', (new SupervisorDriver($runner, 'question-papers-worker', true))->inspect()['transitional']);
        $this->assertSame(['sudo', '-n', 'supervisorctl', 'status', 'question-papers-worker:*'], $runner->calls[0]);

        $fatal = new FakeRunner(['status' => "question-papers-worker:question-papers-worker_00   FATAL   Exited too quickly\n"]);
        $info = (new SupervisorDriver($fatal, 'question-papers-worker'))->inspect();
        $this->assertSame(0, $info['workers']);
        $this->assertStringContainsString('FATAL', (string) $info['detail']);
    }

    public function test_supervisor_that_the_web_user_cannot_reach_is_an_error_not_a_pretended_state(): void
    {
        $driver = new SupervisorDriver(new FakeRunner(['status' => 'error: <class \'socket.error\'>, [Errno 13] Permission denied: file: supervisor.py']), 'question-papers-worker');

        $status = $this->manager($driver)->status();

        $this->assertSame('error', $status['state']);
        $this->assertStringContainsString('could not be read', $status['message']);
    }

    public function test_supervisor_must_return_a_status_for_the_allowlisted_program(): void
    {
        foreach ([
            new FakeRunner(),
            new FakeRunner(['status' => "another-worker:another-worker_00 RUNNING pid 123, uptime 0:01:00\n"]),
            new FakeRunner(['status' => "question-papers-worker: ERROR (no such process)\n"]),
        ] as $runner) {
            try {
                (new SupervisorDriver($runner, 'question-papers-worker'))->inspect();
                $this->fail('A missing or unrelated Supervisor status must not be reported as stopped.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('could not verify', $e->getMessage());
            }
        }
    }

    public function test_systemd_runs_only_its_fixed_commands(): void
    {
        $runner = new FakeRunner(['is-active' => "active\n"]);
        $driver = new SystemdDriver($runner, 'question-papers-worker', true);

        $this->assertSame(1, $driver->inspect()['workers']);
        $driver->start();
        $driver->stop();

        $this->assertSame([
            ['sudo', '-n', 'systemctl', 'is-active', 'question-papers-worker'],
            ['sudo', '-n', 'systemctl', 'start', '--no-block', 'question-papers-worker'],
            ['sudo', '-n', 'systemctl', 'stop', '--no-block', 'question-papers-worker'],
        ], $runner->calls);
        $this->assertSame('stopping', (new SystemdDriver(new FakeRunner(['is-active' => "deactivating\n"]), 'u'))->inspect()['transitional']);
        $this->assertSame('starting', (new SystemdDriver(new FakeRunner(['is-active' => "activating\n"]), 'u'))->inspect()['transitional']);
    }

    public function test_a_unit_or_program_name_that_is_not_a_plain_name_makes_control_unsupported(): void
    {
        foreach ([
            new SupervisorDriver(new FakeRunner(), 'a b; reboot'),
            new SupervisorDriver(new FakeRunner(), 'redis'),
            new SystemdDriver(new FakeRunner(), '../x $(id)'),
            new SystemdDriver(new FakeRunner(), 'ssh.service'),
        ] as $driver) {
            $this->assertNotNull($driver->unsupportedReason());
            $this->assertSame('unsupported', $this->manager($driver)->status()['state']);
        }
    }

    public function test_worker_control_never_uses_a_default_queue_configuration(): void
    {
        config(['queue.connections.question_papers.queue' => 'default']);
        $driver = new FakeDriver();
        $manager = $this->manager($driver);

        $this->assertSame('unsupported', $manager->status()['state']);
        $this->assertSame(501, $manager->start()['http']);
        $this->assertSame(501, $manager->stop()['http']);
        $this->assertSame(0, $driver->startCalls);
        $this->assertSame(0, $driver->stopCalls);
    }

    /* ---------- the manager ---------- */

    public function test_an_unsupported_host_answers_explicitly_and_runs_nothing(): void
    {
        $manager = $this->manager(new UnavailableDriver());

        $this->assertSame('unsupported', $manager->status()['state']);
        $this->assertFalse($manager->status()['supported']);
        $this->assertSame(501, $manager->start()['http']);
        $this->assertSame(501, $manager->stop()['http']);
    }

    public function test_start_reports_running_only_after_a_real_worker_is_verified_and_never_starts_a_second(): void
    {
        $driver = new FakeDriver();
        $manager = $this->manager($driver);
        $this->assertSame('stopped', $manager->status()['state']);

        $first = $manager->start('Admin (7)');
        $this->assertSame(200, $first['http']);
        $this->assertSame(['running', 1], [$first['status']['state'], $first['status']['active_workers']]);
        $this->assertSame('running', $first['status']['desired']);

        $second = $manager->start();
        $this->assertSame(409, $second['http']);
        $this->assertSame(1, $driver->startCalls, 'no duplicate worker was started');
        $this->assertSame(1, $second['status']['active_workers']);
    }

    public function test_a_start_that_does_not_produce_a_worker_is_not_reported_as_running(): void
    {
        $driver = new FakeDriver();
        $driver->startsNothing = true; // the command "succeeds" but no worker process appears
        $manager = $this->manager($driver);

        $result = $manager->start();

        $this->assertSame(202, $result['http'], 'still starting, not running');
        $this->assertSame('starting', $result['status']['state']);
        $this->assertSame(409, $manager->start()['http'], 'an in-progress launch cannot start a second worker');
        $this->assertSame(1, $driver->startCalls);

        // Past the grace period, with nothing there, it is an error with the reason.
        $this->store()->update(['last_action' => ['action' => 'start', 'at' => time() - 120, 'by' => null]]);
        $later = $manager->status();
        $this->assertSame('error', $later['state']);
    }

    public function test_a_start_that_fails_reports_the_real_error(): void
    {
        $driver = new FakeDriver();
        $driver->startError = 'The worker process could not be launched: access denied';

        $result = $this->manager($driver)->start();

        $this->assertSame(500, $result['http']);
        $this->assertSame('access denied', substr($result['status']['last_error'], -13));
        $this->assertNotSame('running', $result['status']['state']);
    }

    public function test_unreadable_process_state_never_enables_a_start_or_claims_a_verified_stop(): void
    {
        $driver = new FakeDriver();
        $driver->inspectError = 'process list unavailable';
        $manager = $this->manager($driver);

        $status = $manager->status();
        $this->assertSame('error', $status['state']);
        $this->assertFalse($status['process_state_readable']);
        $this->assertNull($status['active_workers']);
        $this->assertSame(503, $manager->start()['http']);
        $this->assertSame(503, $manager->stop()['http']);
        $this->assertSame(0, $driver->startCalls);
        $this->assertSame(0, $driver->stopCalls);
    }

    public function test_stop_is_graceful_a_worker_finishing_a_job_is_stopping_not_stopped(): void
    {
        $driver = new FakeDriver();
        $driver->workers = 1;
        $driver->stopLeavesWorker = true; // it is in the middle of a long job
        $manager = $this->manager($driver);

        $result = $manager->stop('Admin (7)');

        $this->assertSame(202, $result['http']);
        $this->assertSame('stopping', $result['status']['state']);
        $this->assertSame('stopped', $result['status']['desired']);

        // Start is refused while it is still finishing: never two workers.
        $this->assertSame(409, $manager->start()['http']);
        $this->assertSame(0, $driver->startCalls);

        // The job ends, the worker exits: only now is it "stopped", and it says it stays stopped.
        $driver->workers = 0;
        $final = $manager->status();
        $this->assertSame('stopped', $final['state']);
        $this->assertStringContainsString('stays stopped', $final['message']);
    }

    public function test_stopping_an_already_stopped_worker_is_a_clean_no_op_that_still_records_the_operators_intent(): void
    {
        $manager = $this->manager(new FakeDriver());

        $result = $manager->stop();

        $this->assertSame(200, $result['http']);
        $this->assertSame(['stopped', 'stopped'], [$result['status']['state'], $result['status']['desired']]);
    }

    public function test_a_manual_stop_overrides_automatic_starting_until_start_is_pressed_again(): void
    {
        $driver = new FakeDriver();
        $driver->workers = 1;
        $manager = $this->manager($driver);

        $manager->stop();
        // Repeated status polls with the worker gone must NOT bring it back.
        for ($i = 0; $i < 4; $i++) {
            $status = $manager->status(reconcile: true);
        }
        $this->assertSame('stopped', $status['state']);
        $this->assertSame(0, $driver->startCalls, 'automatic restart respects the operator\'s stop');

        $manager->start();
        $this->assertSame(1, $driver->startCalls);
        $this->assertSame('running', $manager->status()['state']);
    }

    public function test_an_unexpected_exit_is_an_error_and_is_restarted_within_a_budget_only(): void
    {
        $driver = new FakeDriver();
        $manager = $this->manager($driver);
        $manager->start();
        $this->store()->update(['last_action' => ['action' => 'start', 'at' => time() - 600, 'by' => null]]);

        // Without reconcile it is simply reported.
        $driver->workers = 0; // it crashed
        $error = $manager->status();
        $this->assertSame('error', $error['state']);
        $this->assertStringContainsString('stopped unexpectedly', $error['message']);

        // With reconcile (the panel's own polling) it is restarted, up to MAX_AUTO_RESTARTS times in the window.
        for ($i = 1; $i <= QuestionPaperWorkerManager::MAX_AUTO_RESTARTS; $i++) {
            $driver->workers = 0;
            $this->store()->update(['last_action' => ['action' => 'start', 'at' => time() - 600, 'by' => null]]);
            $status = $manager->status(reconcile: true);
            $this->assertSame('running', $status['state'], "restart {$i}");
        }
        $startsSoFar = $driver->startCalls;

        $driver->workers = 0;
        $this->store()->update(['last_action' => ['action' => 'start', 'at' => time() - 600, 'by' => null]]);
        $spent = $manager->status(reconcile: true);
        $this->assertSame('error', $spent['state'], 'budget spent: it says so instead of looping');
        $this->assertSame($startsSoFar, $driver->startCalls);
    }

    public function test_a_host_that_restarts_the_worker_itself_is_never_restarted_by_the_panel(): void
    {
        $driver = new FakeDriver();
        $driver->managesRestarts = true;
        $manager = $this->manager($driver);
        $manager->start();
        $this->store()->update(['last_action' => ['action' => 'start', 'at' => time() - 600, 'by' => null]]);
        $driver->workers = 0;

        $status = $manager->status(reconcile: true);

        $this->assertSame('error', $status['state']);
        $this->assertSame(1, $driver->startCalls, 'Supervisor/systemd own restarts; the panel does not compete with them');
        $this->assertStringContainsString('configured supervisor restarts', $status['restart_policy']);
    }

    public function test_the_process_table_is_the_truth_not_a_stale_record(): void
    {
        // The record says it was started an hour ago and should be running. Only an unrelated default worker exists.
        $this->store()->update(['desired' => 'running', 'last_action' => ['action' => 'start', 'at' => time() - 3600, 'by' => 'someone']]);
        $table = new FakeProcessTable([777 => '"php" "' . $this->artisanPath() . '" queue:work database --queue=default']);

        $status = $this->manager($this->launcher($table, new FakeRunner()))->status();

        $this->assertSame(0, $status['active_workers'], 'the default worker is not ours');
        $this->assertSame('error', $status['state'], 'and the stale record cannot make it look alive');
    }

    public function test_more_than_one_worker_is_reported_honestly(): void
    {
        $table = new FakeProcessTable([1 => $this->workerLine(), 2 => $this->workerLine()]);

        $status = $this->manager($this->launcher($table, new FakeRunner()))->status();

        $this->assertSame(2, $status['active_workers']);
        $this->assertStringContainsString('2 worker processes', $status['message']);
    }

    public function test_concurrent_start_and_stop_are_refused_while_one_is_in_progress(): void
    {
        $manager = $this->manager(new FakeDriver());
        $handle = fopen($this->stateDir . DIRECTORY_SEPARATOR . 'question-papers-worker.lock', 'c');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB)); // another request is in the middle of a start/stop

        try {
            foreach (['start', 'stop'] as $verb) {
                try {
                    $manager->{$verb}();
                    $this->fail("{$verb} must be refused while another is in progress");
                } catch (WorkerBusy $e) {
                    $this->assertStringContainsString('already in progress', $e->getMessage());
                }
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertSame(200, $manager->start()['http'], 'and it works again once the lock is released');
    }

    public function test_the_status_reports_real_pending_work_the_heartbeat_and_the_last_error(): void
    {
        (require base_path('database/migrations/2026_08_20_000001_create_jobs_table.php'))->up();
        DB::table('jobs')->insert([
            ['queue' => 'question-papers', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'question-papers', 'payload' => '{}', 'attempts' => 1, 'reserved_at' => time(), 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ]);
        $this->makeJob();
        $this->makeJob();
        config(['idms.question_paper.heartbeat_trusted' => true, 'idms.question_paper.heartbeat_key' => 'qp-test-heartbeat:manager']);
        QuestionPaperWorkerHeartbeat::beat(false);
        $this->store()->update(['last_error' => 'something went wrong earlier']);

        $driver = new FakeDriver();
        $driver->workers = 1;
        $status = $this->manager($driver)->status();

        $this->assertSame(['queue_messages' => 1, 'open_jobs' => 2], $status['pending'], 'only unreserved question-papers messages count, never the default queue');
        $this->assertSame('active', $status['heartbeat']['state']);
        $this->assertNotNull($status['heartbeat']['last_beat_at']);
        $this->assertSame('something went wrong earlier', $status['last_error']);
        $this->assertSame('question-papers', $status['queue']);
        \Illuminate\Support\Facades\Cache::forget(QuestionPaperWorkerHeartbeat::key());
    }

    public function test_unknown_counts_are_null_never_a_made_up_zero(): void
    {
        // No `jobs` table in this database.
        $status = $this->manager(new FakeDriver())->status();

        $this->assertNull($status['pending']['queue_messages']);
    }
}
