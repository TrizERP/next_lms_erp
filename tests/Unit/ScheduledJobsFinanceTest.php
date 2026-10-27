<?php

namespace Tests\Unit;

use App\Models\Platform\PlatformScheduledTask;
use App\Services\Platform\ScheduledJobs;
use App\Services\Platform\SchedulerDispatcher;
use Tests\TestCase;

/** Read-only finance and payroll jobs: registered, and honest for a school with no data (tenant -1). */
class ScheduledJobsFinanceTest extends TestCase
{
    private const KEYS = ['fees.defaulter.reminder_run', 'fees.receipt.daily_close', 'hr.payroll.monthly_run'];

    private const NOT_REGISTERED = ['fees.collection.gateway_reconcile', 'hr.staff.document_expiry_scan', 'hr.leave.balance_accrual'];

    public function test_keys_are_registered(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey($key, ScheduledJobs::registry());
            $this->assertTrue(SchedulerDispatcher::hasJob($key), $key);
        }
        // The tasks with no data source are registered by ScheduledJobsPending and only say so.
        foreach (self::NOT_REGISTERED as $key) {
            $this->assertContains($key, \App\Services\Platform\ScheduledJobsPending::keys());
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
