<?php

namespace App\Services\QuestionPapers\Worker;

/**
 * One start/stop at a time. An OS file lock (not the cache), so it holds across processes and web workers whatever the
 * cache driver is, and is released by the OS if the holder dies, so it can never be left locked.
 */
class WorkerLock
{
    public function __construct(private ?string $directory = null)
    {
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws WorkerBusy
     */
    public function run(callable $callback): mixed
    {
        $path = ($this->directory ?? WorkerStateStore::baseDir()) . DIRECTORY_SEPARATOR . 'question-papers-worker.lock';
        $handle = fopen($path, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new WorkerBusy('Another worker start or stop is already in progress. Try again in a moment.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
