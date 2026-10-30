<?php

namespace App\Services\QuestionPapers\Worker;

/**
 * The ONE command the Question Paper worker is ever started with, and how a running process is recognised as it.
 *
 * This is the strict allowlist behind the Start/Stop controls: nothing a client sends is ever part of a command. The
 * program, the working script and every flag are fixed here (the queue name comes from config and is validated), so there
 * is no input that could turn "start the worker" into "run something else". Recognition is just as strict: a process only
 * counts as the Question Paper worker when its command line names THIS checkout's artisan, `queue:work`, the
 * `question_papers` connection and exactly the question-papers queue. A worker for `default` (or any other queue) never
 * matches, so it is never counted and never stopped.
 */
final class WorkerSignature
{
    public const QUEUE = 'question-papers';
    public const SERVICE_NAME = 'question-papers-worker';

    public static function queue(): string
    {
        return self::QUEUE;
    }

    public static function configurationError(): ?string
    {
        return config('queue.connections.question_papers.queue', self::QUEUE) === self::QUEUE
            ? null
            : 'The Question Paper connection must use the dedicated question-papers queue.';
    }

    /**
     * The exact argv of the worker: the same flags as the Supervisor/systemd templates and the development launcher.
     *
     * @return list<string>
     */
    public static function command(string $php, bool $maxTime = true): array
    {
        return array_values(array_filter([
            $php, base_path('artisan'), 'queue:work', 'question_papers', '--queue=' . self::queue(),
            '--sleep=3', '--tries=6', '--timeout=900',
            // The hourly self-restart is for hosts whose service manager brings the worker back; a worker started directly
            // by the local launcher has nothing to restart it, so it runs until it is stopped.
            $maxTime ? '--max-time=3600' : null,
        ]));
    }

    /** Whether a process command line is THIS checkout's Question Paper worker (and nothing else). */
    public static function matches(string $commandLine): bool
    {
        $line = strtolower(str_replace('\\', '/', $commandLine));
        $artisan = strtolower(str_replace('\\', '/', base_path('artisan')));
        $queue = preg_quote(strtolower(self::queue()), '/');
        $phpCommand = preg_match('/^\s*"?[^"]*?php(?:[0-9.]+)?(?:\.exe)?"?\s+/i', $line) === 1;

        return $phpCommand
            && str_contains($line, $artisan)
            && preg_match('/(?<![\w:-])queue:work(?![\w-])/', $line) === 1
            && preg_match('/(?<![\w-])question_papers(?![\w-])/', $line) === 1
            && preg_match('/--queue=' . $queue . '(?=[\s"\']|$)/', $line) === 1;
    }
}
