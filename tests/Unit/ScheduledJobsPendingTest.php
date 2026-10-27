<?php

namespace Tests\Unit;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\ScheduledJobs;
use App\Services\Platform\ScheduledJobsPending;
use App\Services\Platform\SchedulerDispatcher;
use Tests\TestCase;

class ScheduledJobsPendingTest extends TestCase
{
    public function test_every_pending_key_is_a_catalogue_task_with_a_job_and_off_by_default(): void
    {
        $catalogue = (array) config('platform_services.tasks', []);
        foreach (ScheduledJobsPending::keys() as $key) {
            $this->assertArrayHasKey($key, $catalogue, $key);
            $this->assertArrayHasKey($key, ScheduledJobs::registry(), $key);
            $this->assertTrue(SchedulerDispatcher::hasJob($key), $key);
            $this->assertTrue((bool) ($catalogue[$key]['disabled_by_default'] ?? false), $key . ' must be off until someone switches it on');
        }
    }

    public function test_every_catalogue_task_now_has_a_job(): void
    {
        foreach (array_keys((array) config('platform_services.tasks', [])) as $key) {
            $this->assertTrue(SchedulerDispatcher::hasJob($key), $key);
        }
    }

    public function test_a_pending_job_says_nothing_was_checked_and_touches_nothing(): void
    {
        $task = new PlatformScheduledTask();
        $task->sub_institute_id = -1;
        foreach (ScheduledJobsPending::keys() as $key) {
            $task->task_key = $key;
            $out = ScheduledJobsPending::notAvailable($task);
            $this->assertStringStartsWith('Not available:', $out);
            $this->assertStringContainsString('Nothing was checked or changed.', $out);
        }
    }
}
