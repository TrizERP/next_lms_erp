<?php

namespace App\Services\QuestionPapers\Worker;

/** Runs one fixed command (an argv array, never a shell string) and returns its exit code and output. */
interface CommandRunner
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env  extra environment for the child
     * @return array{0: int, 1: string} exit code, combined stdout and stderr
     */
    public function run(array $command, int $timeoutSeconds = 20, array $env = []): array;
}
