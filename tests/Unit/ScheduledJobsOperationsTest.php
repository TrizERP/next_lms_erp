<?php

namespace Tests\Unit;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\ScheduledJobs;
use App\Services\Platform\SchedulerDispatcher;
use Tests\TestCase;

/** Read-only operations jobs: registered, and honest for a school with no data (tenant -1). */
class ScheduledJobsOperationsTest extends TestCase
{
    private const KEYS = ['front_desk.visitor.auto_checkout', 'library.fine.accrue'];

    public function test_keys_are_registered(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey($key, ScheduledJobs::registry());
            $this->assertTrue(SchedulerDispatcher::hasJob($key), $key);
        }
    }

    public function test_jobs_return_a_report_only_sentence_for_an_empty_school(): void
    {
        $task = new PlatformScheduledTask();
        $task->sub_institute_id = -1;
        foreach (self::KEYS as $key) {
            [$class, $method] = ScheduledJobs::registry()[$key];
            $out = $class::$method($task);
            $this->assertIsString($out);
            $this->assertStringStartsWith('Report only:', $out);
        }
    }
}
