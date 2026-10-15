<?php

namespace Tests\Feature\AI\Attendance;

use App\Mcp\ToolRegistry;
use App\Services\Mcp\AttendanceInsightService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `attendance.daily` reports a day's register, and its figures are the table's own.
 *
 * READ-ONLY: every figure is compared with an independent count of the same table.
 */
class AttendanceDailyToolTest extends TestCase
{
    /** @return array{0:McpRequestContext,1:string}|null */
    private function aDayWithRecords(): ?array
    {
        if (! Schema::hasTable('attendance_student')) {
            return null;
        }

        $row = DB::table('attendance_student')
            ->where('attendance_date', '<=', now()->toDateString())
            ->orderByDesc('attendance_date')
            ->first(['sub_institute_id', 'syear', 'attendance_date']);

        return $row === null ? null : [
            new McpRequestContext(1, 'admin', (int) $row->sub_institute_id, [(int) $row->sub_institute_id], null, null, (int) $row->syear, null, true, false),
            (string) $row->attendance_date,
        ];
    }

    public function test_it_is_registered_read_only_and_bound_to_the_attendance_module(): void
    {
        $tool = app(ToolRegistry::class)->tool('attendance.daily');

        $this->assertNotNull($tool);
        $this->assertTrue($tool->definition()['annotations']['read_only']);
        $this->assertContains('attendance.daily', config('ai.lifecycle.modules.attendance.mcp_tools'));
    }

    public function test_the_counts_for_a_day_equal_an_independent_count_of_the_table(): void
    {
        $found = $this->aDayWithRecords();

        if ($found === null) {
            $this->markTestSkipped('No attendance in this estate.');
        }

        [$context, $date] = $found;

        $result = app(AttendanceInsightService::class)->daily($context, ['date' => $date]);

        $expected = DB::table('attendance_student')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('syear', $context->academicYear)
            ->where('attendance_date', $date)
            ->selectRaw("SUM(UPPER(attendance_code) = 'P') p, SUM(UPPER(attendance_code) = 'A') a, COUNT(*) n")
            ->first();

        $this->assertTrue($result['recorded']);
        $this->assertSame((int) $expected->p, $result['present_count']);
        $this->assertSame((int) $expected->a, $result['absent_count']);
        $this->assertSame((int) $expected->n, $result['recorded_students']);
        // `count` is the real total, and the list is cut to the limit.
        $this->assertSame($result['absent_count'], $result['cohort_size']);
        $this->assertSame($result['absent_count'], $result['count']);
        $this->assertLessThanOrEqual(50, count($result['students']));
        $this->assertStringContainsString(number_format($result['absent_count']), $result['headline']);
    }

    public function test_a_day_with_no_records_says_so_and_does_not_report_nobody_absent(): void
    {
        $found = $this->aDayWithRecords();

        if ($found === null) {
            $this->markTestSkipped('No attendance in this estate.');
        }

        [$context] = $found;

        $result = app(AttendanceInsightService::class)->daily($context, ['date' => '1999-01-01']);

        $this->assertFalse($result['recorded']);
        $this->assertSame([], $result['students']);
        $this->assertStringContainsString('No attendance is recorded for 1999-01-01', $result['note']);
        $this->assertArrayNotHasKey('absent_count', $result);
    }

    public function test_an_invalid_date_falls_back_to_today_rather_than_failing(): void
    {
        $found = $this->aDayWithRecords();

        if ($found === null) {
            $this->markTestSkipped('No attendance in this estate.');
        }

        $result = app(AttendanceInsightService::class)->daily($found[0], ['date' => '2026-13-45']);

        $this->assertSame(now()->toDateString(), $result['date']);
    }
}
