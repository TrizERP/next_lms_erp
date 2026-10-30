<?php

namespace App\Services\QuestionPapers\Worker;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Production (Linux, Supervisor): start and stop the ONE configured program through supervisorctl. The program name comes
 * from configuration and is validated; the commands are fixed argv arrays. Supervisor itself restarts a crashed worker and
 * its stop is graceful (SIGTERM, the worker finishes its job), and a stopped program stays stopped until started again.
 *
 * If the web user may not run supervisorctl, `sudo` can be enabled, which needs ONLY these two exact commands in sudoers
 * (see docs/QUESTION_PAPER_DEPLOYMENT_RUNBOOK.md): never general sudo.
 */
class SupervisorDriver implements WorkerDriver
{
    public function __construct(private CommandRunner $runner, private string $program, private bool $sudo = false)
    {
    }

    public function name(): string
    {
        return 'supervisor';
    }

    public function unsupportedReason(): ?string
    {
        return $this->program === WorkerSignature::SERVICE_NAME
            ? null
            : 'Supervisor control is restricted to the question-papers-worker program.';
    }

    public function managesRestarts(): bool
    {
        return true;
    }

    public function inspect(): array
    {
        [$code, $output] = $this->runner->run($this->command('status'), 15);
        $expectedProgram = preg_quote($this->program, '/');
        $workers = 0;
        $transitional = null;
        $detail = null;
        $matched = false;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (preg_match('/^' . $expectedProgram . ':[^\s]+\s+(RUNNING|STOPPED|FATAL|STARTING|STOPPING|BACKOFF|EXITED)\b(.*)$/', trim($line), $match) !== 1) {
                continue;
            }

            $matched = true;
            $state = $match[1];
            if ($state === 'RUNNING') {
                $workers++;
            } elseif ($state === 'STOPPING') {
                $transitional = 'stopping';
            } elseif ($state === 'STARTING' || $state === 'BACKOFF') {
                $transitional = 'starting';
            } elseif ($state === 'FATAL' || $state === 'EXITED') {
                $detail = trim($state . ' ' . $match[2]);
            }
        }

        if ($code !== 0 || !$matched) {
            $reason = trim($output) !== '' ? trim(substr($output, 0, 200)) : 'no status was returned for the configured worker';
            throw new RuntimeException('Supervisor could not verify the configured worker: ' . $reason);
        }

        return ['workers' => $workers, 'transitional' => $transitional, 'detail' => $detail];
    }

    public function start(): void
    {
        [$code, $output] = $this->runner->run($this->command('start'), 30);
        if ($code !== 0 && !str_contains($output, 'already started')) {
            throw new RuntimeException('supervisorctl could not start the worker: ' . trim(substr($output, 0, 300)));
        }
    }

    public function stop(): void
    {
        // supervisorctl stop waits for the job to finish (stopwaitsecs); a client timeout leaves Supervisor stopping it.
        try {
            [$code, $output] = $this->runner->run($this->command('stop'), 20);
        } catch (ProcessTimedOutException $e) {
            if ($this->inspect()['transitional'] === 'stopping') {
                return;
            }

            throw new RuntimeException('Supervisor did not confirm that the worker is stopping: ' . $e->getMessage(), previous: $e);
        }
        if ($code !== 0 && !str_contains($output, 'not running') && !str_contains($output, 'timed out')) {
            throw new RuntimeException('supervisorctl could not stop the worker: ' . trim(substr($output, 0, 300)));
        }
    }

    /** @return list<string> */
    private function command(string $verb): array
    {
        return array_merge($this->sudo ? ['sudo', '-n'] : [], ['supervisorctl', $verb, $this->program . ':*']);
    }
}
