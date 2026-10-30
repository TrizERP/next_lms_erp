<?php

namespace App\Console\Commands;

use App\Services\QuestionPapers\QuestionPaperJobService;
use Illuminate\Console\Command;

/**
 * Recovery for Question Paper jobs: dispatches a bounded batch of unfinished jobs that no worker is
 * running (lost queue message, killed worker, expired lease). It only enqueues; the extraction runs
 * on the queue worker, never inside the scheduler. Re-dispatching a healthy job is harmless.
 */
class DispatchQuestionPapers extends Command
{
    protected $signature = 'idms:dispatch-question-papers {--limit=10 : Most jobs to dispatch in one run}';

    protected $description = 'Dispatch pending or stalled Question Paper extraction jobs to the queue';

    public function handle(QuestionPaperJobService $jobs): int
    {
        if (config('queue.default') === 'sync') {
            $this->warn('QUEUE_CONNECTION is sync; refusing to run extractions inside the scheduler. Use database or redis.');

            return self::FAILURE;
        }

        $limit = max(1, min(50, (int) $this->option('limit')));
        $count = 0;

        foreach ($jobs->dispatchable($limit) as $job) {
            $count += $jobs->dispatch($job) ? 1 : 0;
        }

        $this->info("Dispatched {$count} question paper job(s).");

        return self::SUCCESS;
    }
}
