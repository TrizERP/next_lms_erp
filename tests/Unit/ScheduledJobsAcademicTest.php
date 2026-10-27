<?php

namespace Tests\Unit;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\ScheduledJobs;
use App\Services\Platform\SchedulerDispatcher;
use Tests\TestCase;

/** Read-only academic report jobs: registered, and honest for a school with no data (tenant -1). */
class ScheduledJobsAcademicTest extends TestCase
{
    private const KEYS = [
        'attendance.staff.muster_close',
        'examination.marks.entry_reminder',
        'academics.syllabus.coverage_rollup',
        'lms.content.index_rebuild',
    ];

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
            $this->assertStringStartsWith('Report only:', $out, $key);
        }
    }
}
