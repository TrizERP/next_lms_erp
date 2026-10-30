<?php

namespace App\Jobs;

use App\Services\QuestionPapers\QuestionPaperProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Extracts and saves the questions of one uploaded Question Paper.
 *
 * Carries only the job's UUID: all state lives in question_paper_jobs, so the browser, the web
 * request and this worker share nothing in memory. Safe to dispatch twice (the lease admits one
 * worker). The real retry budget is QuestionPaperProcessor::MAX_ATTEMPTS, counted in the database;
 * $tries is only headroom so the queue does not give up before the database does.
 */
class QuestionPaperExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public const CONNECTION = 'question_papers';

    // Own connection + queue name (config/queue.php) so a worker for this feature never runs
    // unrelated jobs from `default`, and retry_after sits above $timeout.
    public function __construct(public string $jobUuid)
    {
        $this->onConnection(self::CONNECTION)->onQueue((string) config('queue.connections.' . self::CONNECTION . '.queue', 'question-papers'));
    }

    /** @return list<int> seconds to wait before attempt 2, 3, 4... */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(QuestionPaperProcessor $processor): void
    {
        $processor->run($this->jobUuid);
    }

    public function failed(Throwable $exception): void
    {
        app(QuestionPaperProcessor::class)->failPermanently($this->jobUuid, 'Processing stopped: ' . $exception->getMessage());
    }
}
