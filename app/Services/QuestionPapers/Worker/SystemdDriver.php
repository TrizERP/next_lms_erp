<?php

namespace App\Services\QuestionPapers\Worker;

use RuntimeException;

/**
 * Production (Linux, systemd): start and stop the ONE configured unit through systemctl. The unit name comes from
 * configuration and is validated; the commands are fixed argv arrays. systemd restarts a crashed worker (Restart=always)
 * and its stop is graceful (SIGTERM, the worker finishes its job). A stopped unit stays stopped until started again, but
 * comes back at the next reboot unless it is disabled.
 *
 * If the web user may not control the unit, `sudo` can be enabled, which needs ONLY these exact commands in sudoers.
 */
class SystemdDriver implements WorkerDriver
{
    public function __construct(private CommandRunner $runner, private string $unit, private bool $sudo = false)
    {
    }

    public function name(): string
    {
        return 'systemd';
    }

    public function unsupportedReason(): ?string
    {
        return $this->unit === WorkerSignature::SERVICE_NAME
            ? null
            : 'systemd control is restricted to the question-papers-worker unit.';
    }

    public function managesRestarts(): bool
    {
        return true;
    }

    public function inspect(): array
    {
        [, $output] = $this->runner->run(array_merge($this->sudo ? ['sudo', '-n'] : [], ['systemctl', 'is-active', $this->unit]), 15);
        $state = strtolower(trim(strtok($output, "\n") ?: ''));

        if (!in_array($state, ['active', 'inactive', 'failed', 'activating', 'deactivating', 'reloading'], true)) {
            throw new RuntimeException('systemctl is not available to the web user: ' . trim(substr($output, 0, 200)));
        }

        return [
            'workers' => $state === 'active' || $state === 'reloading' ? 1 : 0,
            'transitional' => $state === 'activating' ? 'starting' : ($state === 'deactivating' ? 'stopping' : null),
            'detail' => $state === 'failed' ? 'The service is in a failed state.' : null,
        ];
    }

    public function start(): void
    {
        [$code, $output] = $this->runner->run(array_merge($this->sudo ? ['sudo', '-n'] : [], ['systemctl', 'start', '--no-block', $this->unit]), 20);
        if ($code !== 0) {
            throw new RuntimeException('systemctl could not start the worker: ' . trim(substr($output, 0, 300)));
        }
    }

    public function stop(): void
    {
        [$code, $output] = $this->runner->run(array_merge($this->sudo ? ['sudo', '-n'] : [], ['systemctl', 'stop', '--no-block', $this->unit]), 20);
        if ($code !== 0) {
            throw new RuntimeException('systemctl could not stop the worker: ' . trim(substr($output, 0, 300)));
        }
    }
}
