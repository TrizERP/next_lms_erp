<?php

namespace App\Services\QuestionPapers\Worker;

use Symfony\Component\Process\Process;

class SymfonyCommandRunner implements CommandRunner
{
    public function run(array $command, int $timeoutSeconds = 20, array $env = []): array
    {
        // An argv array: the arguments are passed to the program as they are, never through a shell.
        $process = new Process($command, base_path(), $env === [] ? null : $env);
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }
}
