<?php

namespace App\Services\QuestionPapers\Worker;

/** One way of controlling the Question Paper worker on one kind of host. */
interface WorkerDriver
{
    public function name(): string;

    /** Why this host cannot control the worker, or null when it can. */
    public function unsupportedReason(): ?string;

    /**
     * What is actually there right now.
     *
     * @return array{workers: int, transitional: ?string, detail: ?string} transitional: starting|stopping when the service manager says so
     */
    public function inspect(): array;

    /** @throws \RuntimeException when the worker could not be started */
    public function start(): void;

    /** Ask the worker to stop after the job it is running; never kills it. */
    public function stop(): void;

    /** Whether the host itself restarts a crashed worker (Supervisor, systemd), as opposed to nothing doing so. */
    public function managesRestarts(): bool;
}
