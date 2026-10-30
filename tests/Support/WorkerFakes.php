<?php

namespace Tests\Feature\QuestionPapers;

use App\Services\QuestionPapers\Worker\CommandRunner;
use App\Services\QuestionPapers\Worker\ProcessTable;
use App\Services\QuestionPapers\Worker\WorkerDriver;

/* ---------- test doubles ---------- */

class FakeProcessTable implements ProcessTable
{
    /** @var array<int, string> pid => command line */
    public array $commands;

    /** @param array<int, string> $commands */
    public function __construct(array $commands = [])
    {
        $this->commands = $commands;
    }

    public function php(): array
    {
        $out = [];
        foreach ($this->commands as $pid => $command) {
            $out[] = ['pid' => $pid, 'command' => $command];
        }

        return $out;
    }

    public function commandOf(int $pid): ?string
    {
        return $this->commands[$pid] ?? null;
    }
}

class FakeRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @param array<string, string> $outputByVerb text returned when the argv contains the key */
    public function __construct(private array $outputByVerb = [])
    {
    }

    public function run(array $command, int $timeoutSeconds = 20, array $env = []): array
    {
        $this->calls[] = $command;
        foreach ($this->outputByVerb as $verb => $output) {
            if (in_array($verb, $command, true)) {
                return [0, $output];
            }
        }

        return [0, ''];
    }
}

class FakeDriver implements WorkerDriver
{
    public int $workers = 0;
    public int $startCalls = 0;
    public bool $startsNothing = false;
    public bool $stopLeavesWorker = false;
    public bool $managesRestarts = false;
    public ?string $startError = null;
    public ?string $inspectError = null;
    public int $stopCalls = 0;

    public function name(): string
    {
        return 'fake';
    }

    public function unsupportedReason(): ?string
    {
        return null;
    }

    public function inspect(): array
    {
        if ($this->inspectError !== null) {
            throw new \RuntimeException($this->inspectError);
        }

        return ['workers' => $this->workers, 'transitional' => null, 'detail' => null];
    }

    public function start(): void
    {
        $this->startCalls++;
        if ($this->startError !== null) {
            throw new \RuntimeException($this->startError);
        }
        if (!$this->startsNothing) {
            $this->workers++;
        }
    }

    public function stop(): void
    {
        $this->stopCalls++;
        if (!$this->stopLeavesWorker) {
            $this->workers = 0;
        }
    }

    public function managesRestarts(): bool
    {
        return $this->managesRestarts;
    }
}
