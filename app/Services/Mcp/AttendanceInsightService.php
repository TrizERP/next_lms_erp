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

        $this->narrowByClassNameAndStudents($query, $filters);

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
                // The same figure a person would read aloud. Added beside the fraction
                // rather than replacing it: `attendance_rate` is what detectors and
                // callers compare against a threshold, and a report layout printing
                // "0.7069" in a column headed Rate is not a report anybody would send
                // home. Both are derived from the same division, so they cannot disagree.
                'attendance_percent' => number_format(((int) $row->present_days / $counted) * 100, 1) . '%',
            ];
        }

        usort($judged, static fn (array $a, array $b) => $a['attendance_rate'] <=> $b['attendance_rate']);

        $cohortRate = $judged === []
            ? null
            : round(array_sum(array_column($judged, 'attendance_rate')) / count($judged), 4);

        // Nobody could be judged. Say why, in the tool's own words: an empty list here reads as "no
        // attendance" when the usual cause is a window too short to state a rate.
        $headline = null;

        if ($judged === [] && $rows->isNotEmpty()) {
            $headline = $days < self::MIN_RECORDS
                ? sprintf('A %d-day window is too short to state an attendance rate: a student needs at least %d coded days. Ask for a longer period (the default is %d days).', $days, self::MIN_RECORDS, self::DEFAULT_WINDOW_DAYS)
                : sprintf('None of the %d students has the %d coded days in the last %d days that a rate needs.', $rows->count(), self::MIN_RECORDS, $days);
        } elseif ($rows->isEmpty()) {
            $headline = sprintf('No attendance is recorded for this selection in the last %d days.', $days);
        }

        return array_filter([
            'headline' => $headline,
        ], static fn ($value) => $value !== null) + [
            'window_days' => $days,
            'since' => $since,
            'academic_year' => $context->academicYear,
            'cohort_attendance_rate' => $cohortRate,
            'cohort_attendance_percent' => $cohortRate === null ? null : number_format($cohortRate * 100, 1) . '%',
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
     * Who was present, absent or otherwise marked on ONE day.
     *
     * `overview` is a rate over a window and judges nobody with fewer than five coded days, so a
     * one-day window returns an empty list that reads as "nobody is absent". This is the day's own
     * register: counts for the date, and the students under one status.
     *
     * A day with no records says exactly that, and names the latest day that has some, because
     * "nothing was marked" and "nobody was absent" are different findings.
     *
     * @param  array<string, mixed>  $filters  date (Y-m-d, default today), status (absent|present|other),
     *                                         standard / standard_id, division_id, student_ids, limit
     * @return array<string, mixed>
     */
    public function daily(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('attendance_student')) {
            return ['count' => 0, 'students' => [], 'note' => 'Attendance is not recorded in this estate.'];
        }

        $date = $this->dateFrom($filters['date'] ?? null);
        $status = strtolower(trim((string) ($filters['status'] ?? 'absent')));
        $status = in_array($status, ['absent', 'present', 'other'], true) ? $status : 'absent';
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $base = function () use ($context, $filters) {
            $query = DB::table('attendance_student as a')
                ->join('tblstudent as s', 's.id', '=', 'a.student_id')
                ->leftJoin('standard as st', 'st.id', '=', 'a.standard_id')
                ->leftJoin('division as d', 'd.id', '=', 'a.section_id')
                ->where('a.sub_institute_id', $context->selectedInstituteId);

            if ($context->academicYear !== null) {
                $query->where('a.syear', $context->academicYear);
            }

            foreach (['standard_id' => 'a.standard_id', 'division_id' => 'a.section_id'] as $filter => $column) {
                if (! empty($filters[$filter])) {
                    $query->where($column, (int) $filters[$filter]);
                }
            }

            $this->narrowByClassNameAndStudents($query, $filters);

            return $query;
        };

        $counts = $base()
            ->where('a.attendance_date', $date)
            ->selectRaw(
                "SUM(CASE WHEN UPPER(a.attendance_code) = 'P' THEN 1 ELSE 0 END) AS present_count,
                 SUM(CASE WHEN UPPER(a.attendance_code) = 'A' THEN 1 ELSE 0 END) AS absent_count,
                 SUM(CASE WHEN UPPER(a.attendance_code) NOT IN ('P','A') THEN 1 ELSE 0 END) AS other_count,
                 COUNT(*) AS recorded"
            )
            ->first();

        $recorded = (int) ($counts->recorded ?? 0);

        if ($recorded === 0) {
            $latest = $base()->where('a.attendance_date', '<=', now()->toDateString())->max('a.attendance_date');

            return [
                'date' => $date,
                'recorded' => false,
                'count' => 0,
                'students' => [],
                'latest_recorded_date' => $latest,
                'academic_year' => $context->academicYear,
                'headline' => $latest === null
                    ? sprintf('No attendance is recorded for %s.', $this->dateLabel($date))
                    : sprintf('No attendance is recorded for %s. The latest day with records is %s.', $this->dateLabel($date), $this->dateLabel($latest)),
                'note' => $latest === null
                    ? sprintf('No attendance is recorded for %s, and none is recorded at all for this selection.', $date)
                    : sprintf('No attendance is recorded for %s. The latest day with records is %s.', $date, $latest),
            ];
        }

        $codeTest = match ($status) {
            'present' => "UPPER(a.attendance_code) = 'P'",
            'other' => "UPPER(a.attendance_code) NOT IN ('P','A')",
            default => "UPPER(a.attendance_code) = 'A'",
        };

        $rows = $base()
            ->where('a.attendance_date', $date)
            ->whereRaw($codeTest)
            ->orderBy('st.name')
            ->orderBy('d.name')
            ->orderBy('s.first_name')
            ->limit($limit)
            ->selectRaw(
                "a.student_id,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                 st.name AS standard_name, d.name AS division_name, UPPER(a.attendance_code) AS attendance_code"
            )
            ->get();

        $present = (int) $counts->present_count;
        $absent = (int) $counts->absent_count;
        $other = (int) $counts->other_count;
        $judged = $present + $absent;
        $listed = match ($status) {
            'present' => $present,
            'other' => $other,
            default => $absent,
        };
        $wording = match ($status) {
            'present' => 'present',
            'other' => 'marked otherwise (for example late or on leave)',
            default => 'absent',
        };

        return [
            // The tool's own sentence, so the total in the headline is the real one and not the
            // number of rows the list was cut to.
            'headline' => sprintf(
                '%s %s %s on %s%s%s.',
                number_format($listed),
                $listed === 1 ? 'student was' : 'students were',
                $wording,
                $this->dateLabel($date),
                $judged === 0 ? '' : sprintf(' (%s of %s recorded were present)', number_format($present), number_format($recorded)),
                $listed > $limit ? sprintf('; the first %d are listed', $limit) : ''
            ),
            'date' => $date,
            'recorded' => true,
            'academic_year' => $context->academicYear,
            'status_listed' => $status,
            'recorded_students' => $recorded,
            'present_count' => $present,
            'absent_count' => $absent,
            'other_count' => (int) $counts->other_count,
            'present_percent' => $judged === 0 ? null : number_format(($present / $judged) * 100, 1).'%',
            // The real total behind the list below, which may be cut at `limit`. `count` is the
            // tool's own total by the convention the answer composer reads, not the rows returned.
            'cohort_size' => $listed,
            'count' => $listed,
            'students' => $rows->map(static fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name),
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'attendance_code' => $row->attendance_code,
            ])->all(),
            'rule' => "Codes follow the school's own register: P present, A absent, anything else counted as other.",
        ];
    }

    /**
     * Narrow by a class named in words ("Class 6", "6") and by a list of student ids.
     *
     * Added so a question can say which class it means without first looking up an id, and so
     * "their attendance percentage" can be asked for the students the last answer listed.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Joined `standard as st`.
     * @param  array<string, mixed>  $filters
     */
    private function narrowByClassNameAndStudents($query, array $filters): void
    {
        $standard = self::standardName($filters['standard'] ?? null);

        if ($standard !== null) {
            $query->whereRaw('LOWER(TRIM(st.name)) = ?', [$standard]);
        }

        if (! empty($filters['student_ids']) && is_array($filters['student_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $filters['student_ids']), static fn (int $id) => $id > 0));

            if ($ids !== []) {
                $query->whereIn('a.student_id', array_slice($ids, 0, 200));
            }
        }
    }

    /** "Class 6", "class-6", "Std 6", "6" → "6". Null for nothing usable. */
    public static function standardName(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $name = strtolower(trim((string) $value));
        $name = preg_replace('/^(class|std|standard|grade)[\s\-\.]*/', '', $name) ?? $name;
        $name = trim($name);

        return $name === '' ? null : $name;
    }

    /** "5 October 2026". */
    private function dateLabel(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('j F Y');
    }

    /** A valid Y-m-d date, or today. "today", "yesterday" are accepted so a tool call can say them. */
    private function dateFrom(mixed $value): string
    {
        $raw = strtolower(trim((string) ($value ?? '')));

        if ($raw === '' || $raw === 'today') {
            return now()->toDateString();
        }

        if ($raw === 'yesterday') {
            return now()->subDay()->toDateString();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $raw);

                if ($date !== false && $date->toDateString() === $raw) {
                    return $raw;
                }
            } catch (\Throwable) {
                // fall through to today
            }
        }

        return now()->toDateString();
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
                // Null, not "0.0%", when there are too few days to state a rate — see the
                // note in overview(). A readable figure must not make an unjudgeable
                // student look like a judged one.
                'attendance_percent' => $counted >= self::MIN_RECORDS
                    ? number_format(($present / $counted) * 100, 1) . '%'
                    : null,
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
