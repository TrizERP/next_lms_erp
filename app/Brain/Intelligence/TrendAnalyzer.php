<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Change detection over the LMS's own history.
 *
 * THIS IS THE DIFFERENCE BETWEEN ANALYTICS AND INTELLIGENCE. "Attendance is 85%"
 * is a number. "Class 7 attendance is 84.8% against a school baseline of 89.9%,
 * and it fell four points last month" is a finding somebody can act on. Every
 * method here returns the second shape: a current value, what it is being
 * compared against, and the size and direction of the gap.
 *
 * COMPARISONS ARE ONLY EMITTED WHEN THE DATA CAN SUPPORT THEM. A period with a
 * handful of records is not a trend, it is noise, and reporting "attendance
 * collapsed 48%" off nine marks would be worse than reporting nothing. So every
 * comparison carries a sample size and is suppressed below MIN_SAMPLE — the
 * caller then says "insufficient evidence", which is the honest answer.
 *
 * PARTIAL PERIODS ARE EXCLUDED. The current calendar month is usually half
 * recorded, so comparing it against a complete month manufactures a decline that
 * is really just a shorter month. The most recent COMPLETE month is used as
 * "current" instead, and the window is named in the output so the reader knows
 * what they are looking at.
 */
final class TrendAnalyzer
{
    /** Below this many records a period is noise, not a trend. */
    private const MIN_SAMPLE = 30;

    /** A gap smaller than this is not worth a person's attention. */
    private const MATERIAL_POINTS = 2.0;

    public function __construct(private readonly string $tenantId)
    {
    }

    /**
     * Student attendance rate per month, most recent complete month first.
     *
     * @return array<int, array{period: string, marks: int, present: int, absent: int, rate: float}>
     */
    public function attendanceByMonth(int $months = 13): array
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return [];
        }

        $rows = DB::table('attendance_student')
            ->where('sub_institute_id', $this->tenantId)
            ->whereNotNull('attendance_date')
            ->selectRaw('DATE_FORMAT(attendance_date, "%Y-%m") as period')
            ->selectRaw('COUNT(*) as marks')
            ->selectRaw('SUM(attendance_code = "P") as present')
            ->selectRaw('SUM(attendance_code = "A") as absent')
            ->groupBy('period')
            ->orderByDesc('period')
            ->limit($months)
            ->get();

        $currentMonth = date('Y-m');

        return $rows
            // The month in progress is a partial count; including it as the
            // latest period invents a decline that is only a shorter window.
            ->reject(fn ($row) => (string) $row->period === $currentMonth)
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'marks' => (int) $row->marks,
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'rate' => ((int) $row->marks) > 0 ? round(((int) $row->present) / ((int) $row->marks) * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Attendance this period against last period.
     *
     * @return array{available: bool, reason?: string, current?: array, previous?: array, changePoints?: float, direction?: string, material?: bool}
     */
    public function attendanceTrend(): array
    {
        $months = $this->attendanceByMonth();

        if (count($months) < 2) {
            return ['available' => false, 'reason' => 'Fewer than two complete months of attendance have been recorded.'];
        }

        [$current, $previous] = [$months[0], $months[1]];

        if ($current['marks'] < self::MIN_SAMPLE || $previous['marks'] < self::MIN_SAMPLE) {
            return ['available' => false, 'reason' => 'Too few attendance records in the comparison months to read a trend.'];
        }

        $change = round($current['rate'] - $previous['rate'], 1);

        return [
            'available' => true,
            'current' => $current,
            'previous' => $previous,
            'changePoints' => $change,
            'direction' => $change < 0 ? 'down' : ($change > 0 ? 'up' : 'flat'),
            'material' => abs($change) >= self::MATERIAL_POINTS,
        ];
    }

    /**
     * Attendance rate per class, against the school-wide rate.
     *
     * The baseline is the school's OWN rate, not a national figure or a round
     * number. A class at 84.8% in a school averaging 89.9% is the finding; the
     * same class in a school averaging 84% is not.
     *
     * @return array{baseline: float, marks: int, classes: array<int, array>}
     */
    public function attendanceByClass(): array
    {
        if (! SchemaCache::hasTable('attendance_student') || ! SchemaCache::hasTable('standard')) {
            return ['baseline' => 0.0, 'marks' => 0, 'classes' => []];
        }

        $overall = DB::table('attendance_student')
            ->where('sub_institute_id', $this->tenantId)
            ->selectRaw('COUNT(*) as marks, SUM(attendance_code = "P") as present')
            ->first();

        $marks = (int) ($overall->marks ?? 0);
        $baseline = $marks > 0 ? round(((int) $overall->present) / $marks * 100, 1) : 0.0;

        $classes = DB::table('attendance_student as a')
            ->join('standard as s', 's.id', '=', 'a.standard_id')
            ->where('a.sub_institute_id', $this->tenantId)
            ->selectRaw('a.standard_id, s.name as class_name, s.short_name')
            ->selectRaw('COUNT(*) as marks, SUM(a.attendance_code = "P") as present, SUM(a.attendance_code = "A") as absent')
            ->selectRaw('COUNT(DISTINCT a.student_id) as students')
            ->groupBy('a.standard_id', 's.name', 's.short_name')
            ->having('marks', '>=', self::MIN_SAMPLE)
            ->orderBy('marks', 'desc')
            ->limit(40)
            ->get()
            ->map(function ($row) use ($baseline) {
                $rate = ((int) $row->marks) > 0 ? round(((int) $row->present) / ((int) $row->marks) * 100, 1) : 0.0;

                return [
                    'classId' => (string) $row->standard_id,
                    'className' => $this->className($row->class_name, $row->short_name),
                    'marks' => (int) $row->marks,
                    'absent' => (int) $row->absent,
                    'students' => (int) $row->students,
                    'rate' => $rate,
                    'gapPoints' => round($rate - $baseline, 1),
                ];
            })
            ->all();

        usort($classes, fn ($a, $b) => $a['gapPoints'] <=> $b['gapPoints']);

        return ['baseline' => $baseline, 'marks' => $marks, 'classes' => $classes];
    }

    /**
     * Homework submission rate per month.
     *
     * @return array{available: bool, reason?: string, current?: array, previous?: array, changePoints?: float, direction?: string}
     */
    public function homeworkTrend(): array
    {
        if (! SchemaCache::hasTable('homework')) {
            return ['available' => false, 'reason' => 'This LMS records no homework.'];
        }

        $months = DB::table('homework')
            ->where('sub_institute_id', $this->tenantId)
            ->whereNotNull('date')
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as period, COUNT(*) as total, SUM(completion_status = "Y") as submitted')
            ->groupBy('period')->orderByDesc('period')->limit(13)->get()
            ->reject(fn ($row) => (string) $row->period === date('Y-m'))
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'total' => (int) $row->total,
                'submitted' => (int) $row->submitted,
                'rate' => ((int) $row->total) > 0 ? round(((int) $row->submitted) / ((int) $row->total) * 100, 1) : 0.0,
            ])->values()->all();

        if (count($months) < 2) {
            return ['available' => false, 'reason' => 'Fewer than two complete months of homework have been recorded.'];
        }

        [$current, $previous] = [$months[0], $months[1]];
        $change = round($current['rate'] - $previous['rate'], 1);

        return [
            'available' => true,
            'current' => $current,
            'previous' => $previous,
            'changePoints' => $change,
            'direction' => $change < 0 ? 'down' : ($change > 0 ? 'up' : 'flat'),
            'material' => abs($change) >= self::MATERIAL_POINTS,
        ];
    }

    /**
     * Homework submission per subject, against the school-wide rate.
     *
     * @return array{baseline: float, subjects: array<int, array>}
     */
    public function homeworkBySubject(): array
    {
        if (! SchemaCache::hasTable('homework')) {
            return ['baseline' => 0.0, 'subjects' => []];
        }

        $overall = DB::table('homework')->where('sub_institute_id', $this->tenantId)
            ->selectRaw('COUNT(*) as total, SUM(completion_status = "Y") as submitted')->first();

        $total = (int) ($overall->total ?? 0);
        $baseline = $total > 0 ? round(((int) $overall->submitted) / $total * 100, 1) : 0.0;

        $subjects = DB::table('homework as h')
            ->leftJoin('subject as s', 's.id', '=', 'h.subject_id')
            ->where('h.sub_institute_id', $this->tenantId)
            ->selectRaw('h.subject_id, s.subject_name, COUNT(*) as total, SUM(h.completion_status = "Y") as submitted')
            ->groupBy('h.subject_id', 's.subject_name')
            ->having('total', '>=', 10)
            ->orderByDesc('total')->limit(25)->get()
            ->map(function ($row) use ($baseline) {
                $rate = ((int) $row->total) > 0 ? round(((int) $row->submitted) / ((int) $row->total) * 100, 1) : 0.0;

                return [
                    'subjectId' => (string) $row->subject_id,
                    'subject' => (string) ($row->subject_name ?: ('Subject '.$row->subject_id)),
                    'total' => (int) $row->total,
                    'outstanding' => ((int) $row->total) - ((int) $row->submitted),
                    'rate' => $rate,
                    'gapPoints' => round($rate - $baseline, 1),
                ];
            })->all();

        usort($subjects, fn ($a, $b) => $a['gapPoints'] <=> $b['gapPoints']);

        return ['baseline' => $baseline, 'subjects' => $subjects];
    }

    /**
     * Fee collection per month.
     *
     * @return array{available: bool, reason?: string, current?: array, previous?: array, changePercent?: float, direction?: string}
     */
    public function feeTrend(): array
    {
        if (! SchemaCache::hasTable('fees_collect')) {
            return ['available' => false, 'reason' => 'This LMS records no fee collection.'];
        }

        $months = DB::table('fees_collect')
            ->where('sub_institute_id', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'))
            ->whereNotNull('receiptdate')
            ->selectRaw('DATE_FORMAT(receiptdate, "%Y-%m") as period, COUNT(*) as receipts, COALESCE(SUM(amount),0) as collected')
            ->groupBy('period')->orderByDesc('period')->limit(13)->get()
            ->reject(fn ($row) => (string) $row->period === date('Y-m'))
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'receipts' => (int) $row->receipts,
                'collected' => (float) $row->collected,
            ])->values()->all();

        if (count($months) < 2) {
            return [
                'available' => false,
                'reason' => 'Fewer than two complete months of fee receipts have been recorded in the ERP.',
                'periods' => $months,
            ];
        }

        [$current, $previous] = [$months[0], $months[1]];
        $change = $previous['collected'] > 0
            ? round((($current['collected'] - $previous['collected']) / $previous['collected']) * 100, 1)
            : 0.0;

        return [
            'available' => true,
            'current' => $current,
            'previous' => $previous,
            'changePercent' => $change,
            'direction' => $change < 0 ? 'down' : ($change > 0 ? 'up' : 'flat'),
            'material' => abs($change) >= 5.0,
        ];
    }

    /**
     * Students carrying the most absence, named.
     *
     * @return array<int, array>
     */
    public function chronicAbsentees(int $limit = 25): array
    {
        if (! SchemaCache::hasTable('attendance_student')) {
            return [];
        }

        $rows = DB::table('attendance_student as a')
            ->where('a.sub_institute_id', $this->tenantId)
            ->selectRaw('a.student_id, COUNT(*) as marks, SUM(a.attendance_code = "A") as absent')
            ->groupBy('a.student_id')
            ->having('absent', '>=', (int) config('brain.thresholds.chronic_absence_marks', 5))
            ->orderByDesc('absent')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = DB::table('tblstudent')->whereIn('id', $rows->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'enrollment_no'])->keyBy('id');

        return $rows->map(function ($row) use ($names) {
            $student = $names[$row->student_id] ?? null;
            $marks = (int) $row->marks;

            return [
                'studentId' => (string) $row->student_id,
                'name' => $student ? trim($student->first_name.' '.$student->last_name) : ('Student '.$row->student_id),
                'enrollmentNo' => (string) ($student->enrollment_no ?? ''),
                'absences' => (int) $row->absent,
                'marks' => $marks,
                'rate' => $marks > 0 ? round((($marks - (int) $row->absent) / $marks) * 100, 1) : 0.0,
            ];
        })->all();
    }

    /** Class 7 rather than "7", C-7 rather than nothing. */
    private function className(?string $name, ?string $shortName): string
    {
        $name = trim((string) $name);
        $shortName = trim((string) $shortName);

        if ($name === '') {
            return $shortName !== '' ? $shortName : 'Unnamed class';
        }

        // The LMS stores plain grade numbers ("7"); a bare number reads as an id
        // rather than a class anywhere it is shown on its own.
        return is_numeric($name) ? 'Class '.$name : $name;
    }
}
