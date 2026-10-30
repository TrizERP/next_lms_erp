<?php

namespace App\Services\QuestionPapers\Worker;

/**
 * What the control panel remembers between requests: what the operator last asked for, when, and the last error. Also the
 * stop flag the worker itself honours.
 *
 * It deliberately stores NO process ids. Whether a worker is alive is read from the process table every time, so a stale or
 * recycled pid can never make the panel claim a worker that is not there (or stop a process that is not ours).
 */
class WorkerStateStore
{
    public function __construct(private ?string $directory = null)
    {
    }

    private function dir(): string
    {
        return $this->directory ?? self::baseDir();
    }

    /** Where the state, lock and stop flag live: storage/framework unless QUESTION_PAPER_WORKER_STATE_DIR says otherwise (tests). */
    public static function baseDir(): string
    {
        $configured = config('idms.question_paper.worker.state_dir');

        return is_string($configured) && $configured !== '' ? $configured : storage_path('framework');
    }

    public function path(): string
    {
        return $this->dir() . DIRECTORY_SEPARATOR . 'question-papers-worker.state.json';
    }

    public function stopFlagPath(): string
    {
        return self::flagPath($this->dir());
    }

    public static function flagPath(?string $directory = null): string
    {
        return ($directory ?? self::baseDir()) . DIRECTORY_SEPARATOR . 'question-papers-worker.stop';
    }

    /** @return array{desired: ?string, last_action: ?array{action: string, at: int, by: ?string}, last_error: ?string, restarts: list<int>} */
    public function read(): array
    {
        $default = ['desired' => null, 'last_action' => null, 'last_error' => null, 'restarts' => []];
        clearstatcache(true, $this->path());
        $raw = is_file($this->path()) ? file_get_contents($this->path()) : false;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_merge($default, $decoded) : $default;
    }

    /** @param array<string, mixed> $changes */
    public function update(array $changes): array
    {
        $state = array_merge($this->read(), $changes);
        file_put_contents($this->path(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);

        return $state;
    }

    public function requestStop(): void
    {
        file_put_contents($this->stopFlagPath(), json_encode(['requested_at' => time()]), LOCK_EX);
    }

    public function clearStop(): void
    {
        clearstatcache(true, $this->stopFlagPath());
        if (is_file($this->stopFlagPath())) {
            @unlink($this->stopFlagPath());
        }
    }

    public function stopRequested(): bool
    {
        clearstatcache(true, $this->stopFlagPath());

        return is_file($this->stopFlagPath());
    }

    /** Called by the worker process between jobs: has an operator asked this worker to stop? */
    public static function stopRequestedForWorker(): bool
    {
        $flag = self::flagPath();
        clearstatcache(true, $flag);

        return is_file($flag);
    }
}
