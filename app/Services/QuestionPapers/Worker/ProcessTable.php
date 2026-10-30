<?php

namespace App\Services\QuestionPapers\Worker;

/** What is really running on this machine. The source of truth for "is the worker alive": never a PID file. */
interface ProcessTable
{
    /** @return list<array{pid: int, command: string}> every php process (pid and full command line) */
    public function php(): array;

    /** The full command line of the process with this pid, or null when no such process exists right now. */
    public function commandOf(int $pid): ?string;
}
