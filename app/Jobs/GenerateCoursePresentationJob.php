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
        $runs->execute($this->chapterId, $this->tenant, $this->run);
    }
}
