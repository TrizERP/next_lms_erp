<?php

namespace Tests\Feature\QuestionPapers;

use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperWorkerHeartbeat as Heartbeat;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Is the question-papers worker alive?" Answered from a heartbeat the worker writes, so a paper nobody has picked up can
 * say the worker is down instead of waiting in silence. The real-process proof is in QuestionPaperAutoProcessingTest.
 */
class QuestionPaperWorkerHeartbeatTest extends QuestionPaperTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // In-process cache: trustworthy here because the same process both beats and reads.
        config(['idms.question_paper.heartbeat_trusted' => true, 'idms.question_paper.heartbeat_key' => 'qp-test-heartbeat:unit']);
        Cache::forget(Heartbeat::key());
    }

    private function fakeJob(): Job
    {
        $job = \Mockery::mock(Job::class)->shouldIgnoreMissing();
        // Other listeners on these events (Laravel's queue context, the APM package) read the job's name and payload.
        $job->shouldReceive('payload')->andReturn([]);
        $job->shouldReceive('resolveName', 'getName')->andReturn('FakeJob');

        return $job;
    }

    public function test_without_any_beat_the_worker_is_not_detected(): void
    {
        $this->assertSame('not_detected', Heartbeat::state());
    }

    public function test_an_idle_polling_worker_is_active_and_goes_stale_after_a_minute(): void
    {
        Heartbeat::beat(false);

        $this->assertSame('active', Heartbeat::state());
        $this->assertSame('active', Heartbeat::state(time() + 30));
        $this->assertSame('not_detected', Heartbeat::state(time() + Heartbeat::IDLE_FRESH_SECONDS + 5));
    }

    public function test_a_worker_inside_a_long_job_is_busy_not_missing_until_the_job_timeout_passes(): void
    {
        Heartbeat::beat(true);

        $this->assertSame('busy', Heartbeat::state(time() + 600), 'a 10-minute extraction is not "no worker"');
        $this->assertSame('not_detected', Heartbeat::state(time() + Heartbeat::BUSY_FRESH_SECONDS + 5), 'longer than any job may run: the worker died');
    }

    public function test_only_the_question_papers_connection_counts(): void
    {
        // A worker for another feature's queue polls constantly; it must not make question papers look healthy.
        event(new Looping('database', 'default'));
        event(new Looping('redis', 'course-presentation'));
        $this->assertSame('not_detected', Heartbeat::state());

        event(new Looping('question_papers', 'question-papers'));
        $this->assertSame('active', Heartbeat::state());

        Cache::forget(Heartbeat::key());
        event(new JobProcessing('database', $this->fakeJob()));
        $this->assertSame('not_detected', Heartbeat::state(), 'a default-queue job starting is not our worker');

        event(new JobProcessing('question_papers', $this->fakeJob()));
        $this->assertSame('busy', Heartbeat::state());
        event(new JobProcessed('question_papers', $this->fakeJob()));
        $this->assertSame('active', Heartbeat::state());

        event(new JobProcessing('question_papers', $this->fakeJob()));
        event(new JobFailed('question_papers', $this->fakeJob(), new \RuntimeException('x')));
        $this->assertSame('active', Heartbeat::state(), 'a failed job returns the worker to idle');
    }

    public function test_it_says_unknown_rather_than_false_when_it_cannot_tell(): void
    {
        // The web app cannot see another process's in-memory cache, so silence proves nothing.
        config(['idms.question_paper.heartbeat_trusted' => null, 'cache.default' => 'array']);
        $this->assertNull(Heartbeat::state());

        // An operator can declare the cache untrustworthy (web and worker on different hosts with file caches).
        config(['idms.question_paper.heartbeat_trusted' => false, 'cache.default' => 'file']);
        $this->assertNull(Heartbeat::state());

        // With queue.default=sync nothing can run in the background at all.
        config(['queue.default' => 'sync', 'idms.question_paper.heartbeat_trusted' => true]);
        $this->assertSame('inline', Heartbeat::state());
    }

    public function test_a_queued_job_reports_the_worker_state_and_a_running_one_does_not(): void
    {
        $job = $this->makeJob();
        $jobs = app(QuestionPaperJobService::class);

        $this->assertSame('not_detected', $jobs->present($this->row($job))['worker_state'], 'queued and nobody is polling');

        Heartbeat::beat(false);
        $this->assertSame('active', $jobs->present($this->row($job))['worker_state']);

        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'processing']);
        $this->assertNull($jobs->present($this->row($job))['worker_state'], 'only a queued job needs the explanation');
    }

    private function row(object $job): object
    {
        return DB::table('question_paper_jobs')->where('id', $job->id)->first();
    }
}
