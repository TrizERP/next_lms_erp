<?php

namespace App\Services\QuestionPapers\Worker;

use RuntimeException;

/**
 * Local development: on Windows, a dedicated launcher supervises the allowlisted worker and honours the stop flag between
 * jobs. The worker process is identified from the process table, never from a PID record.
 *
 * Whether the worker is alive is read from the process table (by its exact command line), never from a pid record. If the
 * development launcher script (dev-question-papers.ps1) is already supervising a worker, this driver does not start a
 * second one: it only lifts the stop flag and lets that supervisor bring its worker back.
 */
class LauncherDriver implements WorkerDriver
{
    /** @param array<string, string> $env extra environment for the worker (used by the isolated tests) */
    public function __construct(
        private ProcessTable $processes,
        private WorkerStateStore $store,
        private CommandRunner $runner,
        private array $env = [],
    ) {
    }

    public function name(): string
    {
        return 'launcher';
    }

    public function unsupportedReason(): ?string
    {
        if ($this->phpCli() === null) {
            return 'No PHP command-line binary could be found to start the worker with. Set QUESTION_PAPER_PHP_CLI.';
        }

        if (PHP_OS_FAMILY === 'Windows' && !is_file(base_path('dev-question-papers.ps1'))) {
            return 'The safely managed Windows development launcher is missing.';
        }

        return null;
    }

    public function managesRestarts(): bool
    {
        return true;
    }

    public function inspect(): array
    {
        $workers = 0;
        foreach ($this->processes->php() as $process) {
            if (WorkerSignature::matches($process['command'])) {
                $workers++;
            }
        }

        return ['workers' => $workers, 'transitional' => null, 'detail' => null];
    }

    public function start(): void
    {
        $this->store->clearStop(); // an earlier Stop left the flag; a worker started now would otherwise quit at once

        if ($this->devSupervisorAlive()) {
            return; // its supervisor restarts its worker now that the flag is gone
        }

        $php = $this->phpCli();
        if ($php === null) {
            throw new RuntimeException('No PHP command-line binary could be found.');
        }

        // With an explicit state dir (isolated runs) the logs go there too, so they never overwrite the developer's own worker log.
        $configured = config('idms.question_paper.worker.state_dir');
        $logs = is_string($configured) && $configured !== '' ? $configured : storage_path('logs');
        $workerEnv = $this->env + [
            'QUESTION_PAPER_WORKER_STATE_DIR' => WorkerStateStore::baseDir(),
            'QUESTION_PAPER_PHP_CLI' => $php,
        ];
        [$code, $output] = PHP_OS_FAMILY === 'Windows'
            ? $this->runner->run($this->windowsSupervisorSpawn($logs, $php), 30, $workerEnv)
            : $this->runner->run($this->unixSpawn($php), 30, $workerEnv + ['QP_WORKER_LOG' => $logs . DIRECTORY_SEPARATOR . 'question-papers-worker.log']);

        if ($code !== 0) {
            throw new RuntimeException('The worker process could not be launched: ' . trim(substr($output, 0, 300)));
        }
    }

    public function stop(): void
    {
        // Graceful: the worker finishes the job it is on, starts no other, and exits. Nothing is killed.
        $this->store->requestStop();
    }

    /** @return list<string> */
    private function windowsSupervisorSpawn(string $logs, string $php): array
    {
        $script = base_path('dev-question-papers.ps1');
        $command = '& ' . $this->psQuote($script) . ' -NoServer -StateDirectory ' . $this->psQuote(WorkerStateStore::baseDir()) . ' -Php ' . $this->psQuote($php);
        $utf16 = iconv('UTF-8', 'UTF-16LE', $command);
        if ($utf16 === false) {
            throw new RuntimeException('The Windows worker launcher command could not be encoded.');
        }
        $arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ' . base64_encode($utf16);
        $log = $logs . DIRECTORY_SEPARATOR . 'question-papers-control.log';
        $errorLog = $logs . DIRECTORY_SEPARATOR . 'question-papers-control.err.log';
        if (!is_dir($logs) && !mkdir($logs, 0770, true) && !is_dir($logs)) {
            throw new RuntimeException('The worker log directory could not be created.');
        }

        return [
            'powershell', '-NoProfile', '-NonInteractive', '-Command',
            "Start-Process -FilePath 'powershell.exe' -ArgumentList " . $this->psQuote($arguments) . ' -WorkingDirectory ' . $this->psQuote(base_path())
            . ' -WindowStyle Hidden -RedirectStandardOutput ' . $this->psQuote($log)
            . ' -RedirectStandardError ' . $this->psQuote($errorLog),
        ];
    }

    /** @return list<string> */
    private function unixSpawn(string $php): array
    {
        $supervisor = 'stop=$1; shift; restarts=0; window=$(date +%s); '
            . 'while [ ! -f "$stop" ]; do "$@"; code=$?; [ -f "$stop" ] && exit 0; '
            . 'now=$(date +%s); if [ "$((now-window))" -gt 600 ]; then restarts=0; window=$now; fi; '
            . 'restarts=$((restarts+1)); [ "$restarts" -gt 3 ] && exit "$code"; sleep "$((1 << restarts))"; done';

        // The worker command and stop path stay positional arguments; only this fixed supervisor loop is interpreted by sh.
        return array_merge([
            'sh', '-c',
            'script=$1; stop=$2; shift 2; nohup sh -c "$script" question-paper-supervisor "$stop" "$@" >> "$QP_WORKER_LOG" 2>&1 &',
            'question-paper-launcher', $supervisor, $this->store->stopFlagPath(),
        ], WorkerSignature::command($php, false));
    }

    private function psQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /** Whether dev-question-papers.ps1 is running for this checkout, verified by the process's own command line, not by a pid file. */
    private function devSupervisorAlive(): bool
    {
        $file = WorkerStateStore::baseDir() . DIRECTORY_SEPARATOR . 'dev-question-papers.json';
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $pid = is_array($decoded) ? (int) ($decoded['supervisor'] ?? 0) : 0;
        $command = $pid > 0 ? $this->processes->commandOf($pid) : null;

        return $command !== null && str_contains(strtolower($command), 'dev-question-papers');
    }

    private function phpCli(): ?string
    {
        $candidates = [
            config('idms.question_paper.worker.php_cli'),
            in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? PHP_BINARY : null,
            PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php'),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
