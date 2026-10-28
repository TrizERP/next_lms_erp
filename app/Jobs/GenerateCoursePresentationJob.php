<?php

namespace App\Jobs;

use App\Services\CoursePresentation\GenerationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one Course Presentation draft in the background (see GenerationRun). A generation takes a long time, so it
 * never runs inside the teacher's request when a queue worker is available. It stores nothing: the draft waits in its
 * review folder until a teacher approves it. Unique per chapter and school, so a double click is one run.
 */
class GenerateCoursePresentationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 7200;
    public int $uniqueFor = 7200;

    public function __construct(public int $chapterId, public int $tenant, public string $run)
    {
    }

    public function uniqueId(): string
    {
        return "course-presentation:{$this->tenant}:{$this->chapterId}";
    }

    public function handle(GenerationRun $runs): void
    {
        @set_time_limit(0);
        // A worker that was started before the pipeline's code was changed still runs the OLD code. Better a clear refusal at once than a run that
        // follows rules the code no longer has (this is how a run once spent most of an hour in one stage).
        if (config('course_presentation_pilot.generation.worker_guard', true) && $runs->codeChangedSince((int) ($_SERVER['REQUEST_TIME'] ?? 0))) {
            $runs->failRun($this->chapterId, $this->tenant, $this->run, 'The queue worker was started before the generation code was changed, so it would run the old code. Restart the queue worker (php artisan queue:work) and try again.');

            return;
        }
        $runs->execute($this->chapterId, $this->tenant, $this->run);
    }
}
