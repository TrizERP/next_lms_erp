<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance, as rates rather than rows.
 *
 * Follows the estate's own coding exactly as the risk detectors do: `attendance_code`
 * 'P' is present, 'A' is absent, and anything else is neither. That third case matters
 * and is reported rather than folded away — a school using 'L' for late or 'H' for a
 * holiday would otherwise see those days silently counted against a child's rate.
 *
 * The rate is `present / (present + absent)`, so uncoded days never move it. A cohort
 * with too few records to judge is reported as such rather than as 0%, because "we have
 * not recorded enough to say" and "this class never turns up" are very different
 * findings and only one of them is about the children.
 */
class AttendanceInsightService
{
    /** Below this many records, a rate is noise rather than a finding. */
    private const MIN_RECORDS = 5;

    private const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Attendance across a cohort, worst first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('attendance_student')) {
            return ['count' => 0, 'students' => [], 'note' => 'Attendance is not recorded in this estate.'];
        }

        $days = min(max((int) ($filters['days'] ?? self::DEFAULT_WINDOW_DAYS), 1), 365);
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $since = now()->subDays($days)->toDateString();

        $query = DB::table('attendance_student as a')
            ->join('tblstudent as s', 's.id', '=', 'a.student_id')
            ->leftJoin('standard as st', 'st.id', '=', 'a.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'a.section_id')
            ->where('a.sub_institute_id', $context->selectedInstituteId)
            ->where('a.attendance_date', '>=', $since);

        if ($context->academicYear !== null) {
            $query->where('a.syear', $context->academicYear);
        }

        foreach (['standard_id' => 'a.standard_id', 'division_id' => 'a.section_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['student_id'])) {
            $query->where('a.student_id', (int) $filters['student_id']);
        }

        $rows = $query
            ->selectRaw(
                "a.student_id,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 st.name AS standard_name, d.name AS division_name,
                 SUM(CASE WHEN UPPER(a.attendance_code) = 'P' THEN 1 ELSE 0 END) AS present_days,
                 SUM(CASE WHEN UPPER(a.attendance_code) = 'A' THEN 1 ELSE 0 END) AS absent_days,
                 SUM(CASE WHEN UPPER(a.attendance_code) NOT IN ('P','A') THEN 1 ELSE 0 END) AS other_days,
                 COUNT(*) AS recorded_days"
            )
            ->groupBy('a.student_id', 's.first_name', 's.middle_name', 's.last_name', 'st.name', 'd.name')
            ->get();

        $judged = [];
        $insufficient = 0;

        foreach ($rows as $row) {
            $counted = (int) $row->present_days + (int) $row->absent_days;

            if ($counted < self::MIN_RECORDS) {
                $insufficient++;

                continue;
            }

            $judged[] = [
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name),
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'present_days' => (int) $row->present_days,
                'absent_days' => (int) $row->absent_days,
                'uncoded_days' => (int) $row->other_days,
                'attendance_rate' => round((int) $row->present_days / $counted, 4),
            ];
        }

        usort($judged, static fn (array $a, array $b) => $a['attendance_rate'] <=> $b['attendance_rate']);

        $cohortRate = $judged === []
            ? null
            : round(array_sum(array_column($judged, 'attendance_rate')) / count($judged), 4);

        return [
            'window_days' => $days,
            'since' => $since,
            'academic_year' => $context->academicYear,
            'cohort_attendance_rate' => $cohortRate,
            'students_judged' => count($judged),
            'students_with_insufficient_data' => $insufficient,
            'count' => min(count($judged), $limit),
            'students' => array_slice($judged, 0, $limit),
            'rule' => sprintf(
                "Rate is present / (present + absent). A student needs at least %d coded days "
                . 'before a rate is reported; %d had fewer and are excluded rather than shown as zero.',
                self::MIN_RECORDS,
                $insufficient
            ),
        ];
    }

    /**
     * One student's own record, day by day.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forStudent(McpRequestContext $context, array $filters): array
    {
        $studentId = (int) ($filters['student_id'] ?? 0);

        if ($studentId <= 0) {
            return ToolResult::failure(
                'attendance.student',
                'A valid student is required.',
                'MISSING_STUDENT_ID'
            );
        }

        if (! Schema::hasTable('attendance_student')) {
            return ToolResult::failure(
                'attendance.student',
                'Attendance is not recorded in this estate.',
                'NO_ATTENDANCE_TABLE'
            );
        }

        $days = min(max((int) ($filters['days'] ?? self::DEFAULT_WINDOW_DAYS), 1), 365);
        $since = now()->subDays($days)->toDateString();

        $query = DB::table('attendance_student')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('student_id', $studentId)
            ->where('attendance_date', '>=', $since);

        if ($context->academicYear !== null) {
            $query->where('syear', $context->academicYear);
        }

        $records = $query
            ->orderByDesc('attendance_date')
            ->limit(400)
            ->get(['id', 'attendance_date', 'attendance_code', 'standard_id', 'section_id']);

        $present = $records->filter(static fn ($r) => strtoupper((string) $r->attendance_code) === 'P')->count();
        $absent = $records->filter(static fn ($r) => strtoupper((string) $r->attendance_code) === 'A')->count();
        $counted = $present + $absent;

        $absences = $records
            ->filter(static fn ($r) => strtoupper((string) $r->attendance_code) === 'A')
            ->map(static fn ($r) => $r->attendance_date)
            ->values()
            ->all();

        return ToolResult::success(
            'attendance.student',
            $counted === 0
                ? 'No coded attendance was recorded for this student in the window.'
                : sprintf('Attendance is %.0f%% over the last %d days.', ($present / $counted) * 100, $days),
            [
                'student_id' => $studentId,
                'window_days' => $days,
                'since' => $since,
                'present_days' => $present,
                'absent_days' => $absent,
                'uncoded_days' => $records->count() - $counted,
                'attendance_rate' => $counted >= self::MIN_RECORDS ? round($present / $counted, 4) : null,
                'absence_dates' => $absences,
                'judgeable' => $counted >= self::MIN_RECORDS,
                'note' => $counted >= self::MIN_RECORDS
                    ? null
                    : sprintf(
                        'Only %d coded day%s on record — too few to state a rate. This says nothing '
                        . 'about the student, only about what has been captured.',
                        $counted,
                        $counted === 1 ? '' : 's'
                    ),
            ]
        );
    }
}
