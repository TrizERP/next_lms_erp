<?php

namespace App\Services\QuestionPapers\Worker;

use RuntimeException;

/**
 * This host has no safe way to control the worker from the application (for example a production server where no service
 * control has been configured). Every control answers "unsupported" instead of pretending, and nothing is ever executed.
 */
class UnavailableDriver implements WorkerDriver
{
    public function __construct(private string $reason = 'Worker control is not configured on this server. Start and stop the worker with the service manager (see the deployment runbook).')
    {
    }

    public function name(): string
    {
        return 'none';
    }

    public function unsupportedReason(): ?string
    {
        return $this->reason;
    }

    public function managesRestarts(): bool
    {
        return false;
    }

    public function inspect(): array
    {
        return ['workers' => 0, 'transitional' => null, 'detail' => null];
    }

    public function start(): void
    {
        throw new RuntimeException($this->reason);
    }

    public function stop(): void
    {
        throw new RuntimeException($this->reason);
    }
}
