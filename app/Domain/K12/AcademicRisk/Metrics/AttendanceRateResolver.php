<?php

namespace App\Domain\K12\AcademicRisk\Metrics;

use App\Domain\AI\Outcomes\MetricResolver;
use App\Domain\K12\AcademicRisk\AcademicRiskMetrics;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a student's present-day rate over a recent window.
 *
 * Expressed as *present* percent rather than absent percent so the direction of a
 * good outcome is "increase", consistent with every other metric here. Mixing
 * directions across metrics is how outcome dashboards end up quietly wrong.
 */
class AttendanceRateResolver implements MetricResolver
{
    private const LOOKBACK_DAYS = 30;

    private const MIN_RECORDS = 3;

    public function metricKey(): string
    {
        return AcademicRiskMetrics::ATTENDANCE_RATE;
    }

    public function label(): string
    {
        return 'Attendance (% present)';
    }

    public function resolve(string $subjectEntityKey, int|string $subjectId, McpRequestContext $scope): ?float
    {
        if ($subjectEntityKey !== 'student' || ! Schema::hasTable('attendance_student')) {
            return null;
        }

        $query = DB::table('attendance_student')
            ->where('student_id', (int) $subjectId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('attendance_date', '>=', now()->subDays(self::LOOKBACK_DAYS)->toDateString());

        if ($scope->academicYear !== null && Schema::hasColumn('attendance_student', 'syear')) {
            $query->where('syear', $scope->academicYear);
        }

        $records = $query->get(['attendance_code']);

        if ($records->count() < self::MIN_RECORDS) {
            return null;
        }

        $present = $records->filter(
            fn ($row) => strtoupper((string) $row->attendance_code) === 'P'
        )->count();

        return round(($present / $records->count()) * 100, 2);
    }
}
