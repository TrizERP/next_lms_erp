<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Analytics computed from vivek_erp, not from a snapshot of it.
 *
 * EVERY NUMBER ON EVERY CHART IS A GROUP BY AGAINST THE LIVE LMS TABLES, scoped
 * to one sub_institute_id. Nothing is seeded, cached into a summary table, or
 * carried over from the last run — which is why a department renamed in the LMS
 * this morning appears under its new name here this afternoon.
 *
 * AGGREGATION HAPPENS IN SQL, NEVER IN PHP. The student roll for one institute is
 * 3,438 rows and the staff attendance log is 356,872; pulling either into memory
 * to count it would work today and fall over on the institute that has ten times
 * as many. Every method below returns at most a few dozen rows.
 *
 * A SECTION THAT HAS NO DATA RETURNS AN EMPTY SERIES, NOT A ZERO-FILLED ONE. An
 * institute that has never recorded a mark should see an empty academic chart
 * and know why, rather than a flat line at zero that looks like measured
 * failure.
 */
final class LmsAnalytics
{
    use LmsQueryScope;

    public function __construct(private readonly string $tenantId)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'generatedAt' => now()->toIso8601String(),
            'source' => DB::connection()->getDatabaseName(),
            'headline' => $this->headline(),
            'organization' => $this->organizationAnalytics(),
            'people' => $this->peopleAnalytics(),
            'students' => $this->studentAnalytics(),
            'attendance' => $this->attendanceAnalytics(),
            'academics' => $this->academicAnalytics(),
            'finance' => $this->financeAnalytics(),
            'intelligence' => $this->intelligenceAnalytics(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function headline(): array
    {
        return [
            $this->tile('departments', 'Departments', $this->lmsCount('hrms_departments'), 'hrms_departments'),
            $this->tile('people', 'Staff', $this->countWithProfile('tbluser'), 'tbluser'),
            $this->tile('students', 'Students', $this->lmsCount('tblstudent'), 'tblstudent'),
            $this->tile('subjects', 'Subjects', $this->lmsCount('subject'), 'subject'),
            $this->tile('signals', 'Open signals', $this->brainCount('hpbrain_signals'), 'hpbrain_signals'),
            $this->tile('recommendations', 'Recommendations', $this->brainCount('hpbrain_recommendations'), 'hpbrain_recommendations'),
        ];
    }

    /** @return array<string, mixed> */
    private function organizationAnalytics(): array
    {
        return [
            'staffByDepartment' => $this->staffByDepartment(),
            'departmentCompleteness' => $this->departmentCompleteness(),
        ];
    }

    /**
     * Headcount per department, largest first.
     *
     * The join is to hrms_departments so a department that has been renamed or
     * soft-deleted since a staff record was written still resolves to a name
     * rather than a bare id.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function staffByDepartment(): array
    {
        if (! $this->has('tbluser') || ! $this->has('hrms_departments')) {
            return [];
        }

        // The join is scoped to this institute's departments as well as its
        // staff: department_id is not a tenant-scoped foreign key here, and an
        // unscoped join labels this school's staff with another school's
        // department names.
        $q = $this->lmsPeople()
            ->leftJoin('hrms_departments as d', function ($join) {
                $join->on('d.id', '=', 'tbluser.department_id')
                    ->where('d.sub_institute_id', '=', $this->tenantId);
            })
            ->select(DB::raw('COALESCE(d.department, "Unassigned") as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')->orderByDesc('value')->limit(15)->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value])->all();

        return $q;
    }

    /** @return array<int, array{label: string, value: int}> */
    private function departmentCompleteness(): array
    {
        if (! $this->has('hrms_departments')) {
            return [];
        }

        $base = fn () => $this->lmsDepartments();

        $total = (int) $base()->count();
        $withHead = (int) $base()->whereNotNull('hrms_departments.head_user_id')->where('hrms_departments.head_user_id', '>', 0)->count();
        $withDescription = SchemaCache::hasColumn('hrms_departments', 'description')
            ? (int) $base()->whereNotNull('hrms_departments.description')->where('hrms_departments.description', '!=', '')->count()
            : 0;

        $occupied = $this->has('tbluser')
            ? (int) $this->lmsPeople()
                ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
                ->where('d.sub_institute_id', $this->tenantId)
                ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('d.status', 1))
                ->distinct()->count('d.id')
            : 0;

        return [
            ['label' => 'Has an accountable head', 'value' => $withHead],
            ['label' => 'Has a written remit', 'value' => $withDescription],
            ['label' => 'Carries staff', 'value' => min($occupied, $total)],
            ['label' => 'Total departments', 'value' => $total],
        ];
    }

    /** @return array<string, mixed> */
    private function peopleAnalytics(): array
    {
        if (! $this->has('tbluser')) {
            return [];
        }

        $total = $this->lmsCount('tbluser');
        $scoped = fn () => $this->lmsPeople();

        return [
            'byGender' => $this->breakdown('tbluser', 'gender', 'sub_institute_id', 8),
            'byStatus' => $this->labelled($this->breakdown('tbluser', 'status', 'sub_institute_id', 5), [
                '1' => 'Active', '0' => 'Inactive',
            ]),
            'recordCompleteness' => [
                ['label' => 'Has email', 'value' => (int) $scoped()->whereNotNull('tbluser.email')->where('tbluser.email', '!=', '')->count()],
                ['label' => 'Has mobile', 'value' => (int) $scoped()->whereNotNull('tbluser.mobile')->where('tbluser.mobile', '!=', '')->count()],
                ['label' => 'Has a department', 'value' => (int) $scoped()->whereNotNull('tbluser.department_id')->where('tbluser.department_id', '>', 0)->count()],
                ['label' => 'Has signed in', 'value' => SchemaCache::hasColumn('tbluser', 'last_login')
                    ? (int) $scoped()->whereNotNull('tbluser.last_login')->where('tbluser.last_login', '!=', '')->count()
                    : 0],
                ['label' => 'Total staff', 'value' => $total],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function studentAnalytics(): array
    {
        if (! $this->has('tblstudent')) {
            return [];
        }

        // A FRESH BUILDER PER FIGURE. Query builders are mutable, so reusing one
        // instance made each completeness figure inherit the previous one's
        // WHERE clause: "has date of birth" silently meant "has an enrollment
        // number AND a date of birth", and the admission-year grouping left a
        // GROUP BY on the builder that the counts after it then inherited.
        $scoped = fn () => $this->lmsStudents();

        return [
            'byGender' => $this->breakdown('tblstudent', 'gender', 'sub_institute_id', 8),
            'byAdmissionYear' => $scoped()
                ->whereNotNull('tblstudent.admission_year')->where('tblstudent.admission_year', '>', 0)
                ->select('tblstudent.admission_year as label', DB::raw('COUNT(*) as value'))
                ->groupBy('tblstudent.admission_year')->orderBy('tblstudent.admission_year')->limit(20)->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value])->all(),
            'recordCompleteness' => [
                ['label' => 'Has enrollment number', 'value' => (int) $scoped()->whereNotNull('tblstudent.enrollment_no')->where('tblstudent.enrollment_no', '!=', '')->count()],
                ['label' => 'Has date of birth', 'value' => (int) $scoped()->whereNotNull('tblstudent.dob')->count()],
                ['label' => 'Has a contact number', 'value' => (int) $scoped()->where(function ($q) {
                    $q->where('tblstudent.mobile', '!=', '')->orWhere('tblstudent.student_mobile', '!=', '')->orWhere('tblstudent.mother_mobile', '!=', '');
                })->count()],
                ['label' => 'Total students', 'value' => $this->lmsCount('tblstudent')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function attendanceAnalytics(): array
    {
        $out = [];

        if ($this->has('attendance_student')) {
            $out['studentByCode'] = $this->labelled(
                $this->breakdown('attendance_student', 'attendance_code', 'sub_institute_id', 10),
                ['P' => 'Present', 'A' => 'Absent', 'L' => 'Leave', 'H' => 'Half day']
            );

            // Monthly trend, computed in SQL. The 24-month window keeps the
            // series readable and bounds the scan on an institute with years of
            // history.
            $out['studentMonthlyTrend'] = DB::table('attendance_student')
                ->where('sub_institute_id', $this->tenantId)
                ->whereNotNull('attendance_date')
                ->select(
                    DB::raw('DATE_FORMAT(attendance_date, "%Y-%m") as label'),
                    DB::raw('SUM(attendance_code = "P") as present'),
                    DB::raw('SUM(attendance_code = "A") as absent'),
                    DB::raw('COUNT(*) as value')
                )
                ->groupBy('label')->orderByDesc('label')->limit(24)->get()
                ->reverse()->values()
                ->map(fn ($r) => [
                    'label' => (string) $r->label,
                    'value' => (int) $r->value,
                    'present' => (int) $r->present,
                    'absent' => (int) $r->absent,
                    'attendanceRate' => ((int) $r->value) > 0 ? round(((int) $r->present) / ((int) $r->value) * 100, 1) : 0,
                ])->all();
        }

        if ($this->has('hrms_attendances')) {
            $out['staffMonthlyTrend'] = DB::table('hrms_attendances')
                ->where('sub_institute_id', $this->tenantId)
                ->whereNotNull('day')
                ->select(
                    DB::raw('DATE_FORMAT(day, "%Y-%m") as label'),
                    DB::raw('COUNT(*) as value'),
                    DB::raw('COUNT(DISTINCT user_id) as staff'),
                    DB::raw('SUM(punchout_time IS NULL) as open_punches')
                )
                ->groupBy('label')->orderByDesc('label')->limit(18)->get()
                ->reverse()->values()
                ->map(fn ($r) => [
                    'label' => (string) $r->label,
                    'value' => (int) $r->value,
                    'staff' => (int) $r->staff,
                    'openPunches' => (int) $r->open_punches,
                ])->all();
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function academicAnalytics(): array
    {
        $out = [];

        if ($this->has('result_marks')) {
            $out['bySubject'] = DB::table('result_marks')
                ->where('sub_institute_id', $this->tenantId)
                ->whereNotNull('subject_name')->where('subject_name', '!=', '')
                ->select('subject_name as label', DB::raw('ROUND(AVG(per), 2) as value'), DB::raw('COUNT(*) as records'))
                ->groupBy('subject_name')->orderByDesc('value')->limit(20)->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (float) $r->value, 'records' => (int) $r->records])->all();

            $out['byGrade'] = $this->breakdown('result_marks', 'grade', 'sub_institute_id', 12);

            // Attainment bands. Chosen to match the institute's own pass mark
            // (config brain.thresholds.pass_percentage) rather than an arbitrary
            // quartile split, so the "below pass" band means what the school
            // means by it.
            $pass = (float) config('brain.thresholds.pass_percentage', 40.0);
            $out['attainmentBands'] = DB::table('result_marks')
                ->where('sub_institute_id', $this->tenantId)
                ->select(DB::raw(sprintf(
                    'CASE WHEN per < %1$f THEN "Below pass" WHEN per < 60 THEN "Pass" WHEN per < 75 THEN "Merit" ELSE "Distinction" END as label',
                    $pass
                )), DB::raw('COUNT(*) as value'))
                ->groupBy('label')->orderByDesc('value')->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value])->all();
        }

        if ($this->has('homework')) {
            $out['homeworkCompletion'] = $this->labelled(
                $this->breakdown('homework', 'completion_status', 'sub_institute_id', 6),
                ['Y' => 'Submitted', 'N' => 'Outstanding']
            );
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function financeAnalytics(): array
    {
        if (! $this->has('fees_collect')) {
            return [];
        }

        $live = fn () => DB::table('fees_collect')->where('sub_institute_id', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'));

        $totals = $live()->selectRaw('COUNT(*) as receipts, COALESCE(SUM(amount),0) as collected, COALESCE(SUM(fine),0) as fines, COALESCE(SUM(fees_discount),0) as discounts')->first();

        return [
            'totals' => [
                ['label' => 'Receipts', 'value' => (int) ($totals->receipts ?? 0)],
                ['label' => 'Collected (INR)', 'value' => (float) ($totals->collected ?? 0)],
                ['label' => 'Fines (INR)', 'value' => (float) ($totals->fines ?? 0)],
                ['label' => 'Discounts (INR)', 'value' => (float) ($totals->discounts ?? 0)],
            ],
            'byPaymentMode' => $live()
                ->select(DB::raw('COALESCE(NULLIF(payment_mode, ""), "Unspecified") as label'), DB::raw('COUNT(*) as value'), DB::raw('COALESCE(SUM(amount),0) as amount'))
                ->groupBy('label')->orderByDesc('value')->limit(10)->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value, 'amount' => (float) $r->amount])->all(),
            'monthlyTrend' => $live()
                ->whereNotNull('receiptdate')
                ->select(DB::raw('DATE_FORMAT(receiptdate, "%Y-%m") as label'), DB::raw('COALESCE(SUM(amount),0) as value'), DB::raw('COUNT(*) as receipts'))
                ->groupBy('label')->orderByDesc('label')->limit(18)->get()
                ->reverse()->values()
                ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (float) $r->value, 'receipts' => (int) $r->receipts])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function intelligenceAnalytics(): array
    {
        return [
            'signalsBySeverity' => $this->brainBreakdown('hpbrain_signals', 'severity'),
            'signalsByClassification' => $this->brainBreakdown('hpbrain_signals', 'classification', 15),
            // Grouped by the AREA OF THE SCHOOL a finding came from, not by the
            // table it was read out of: "Attendance" is what a principal is
            // looking for, `vivek_erp.attendance_student` is not.
            'signalsByArea' => $this->byArea($this->brainBreakdown('hpbrain_signals', 'source', 30)),
            'rootCauseFamilies' => $this->brainBreakdown('hpbrain_hypotheses', 'root_cause_family', 15),
            'recommendationsByCategory' => $this->brainBreakdown('hpbrain_recommendations', 'category'),
            'recommendationsByPriority' => $this->brainBreakdown('hpbrain_recommendations', 'priority'),
            'knowledgeByCategory' => $this->brainBreakdown('hpbrain_knowledge_assets', 'category', 15),
            'loopStages' => $this->loopStages(),
            'confidenceDistribution' => $this->confidenceDistribution(),
        ];
    }

    /** @return array<int, array{label: string, value: int}> */
    private function loopStages(): array
    {
        $stages = [
            'Signal' => 'hpbrain_signals',
            'Evidence' => 'hpbrain_evidence',
            'Case' => 'hpbrain_cases',
            'Hypothesis' => 'hpbrain_hypotheses',
            'Reasoning' => 'hpbrain_reasoning_steps',
            'Recommendation' => 'hpbrain_recommendations',
            'Decision' => 'hpbrain_decisions',
            'Execution' => 'hpbrain_eso_executions',
            'Outcome' => 'hpbrain_outcomes',
        ];

        $out = [];
        foreach ($stages as $label => $table) {
            $out[] = ['label' => $label, 'value' => $this->brainCount($table), 'table' => $table];
        }

        return $out;
    }

    /** @return array<int, array{label: string, value: int}> */
    private function confidenceDistribution(): array
    {
        if (! $this->has('hpbrain_recommendations')) {
            return [];
        }

        return DB::table('hpbrain_recommendations')
            ->where('tenant_id', $this->tenantId)
            ->select(DB::raw('CASE
                WHEN confidence < 0.45 THEN "Low (< 0.45)"
                WHEN confidence < 0.60 THEN "Moderate (0.45–0.60)"
                WHEN confidence < 0.80 THEN "Strong (0.60–0.80)"
                ELSE "Very strong (≥ 0.80)" END as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')->orderByDesc('value')->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value])->all();
    }

    /* ================================================================ helpers */

    private function has(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    /** Named alias for readability; the join lives in LmsQueryScope::lmsCount(). */
    private function countWithProfile(string $table): int
    {
        return $this->lmsCount($table);
    }

    /**
     * The active population of one LMS table, as the LMS itself defines it.
     *
     * Departments, staff and students have business rules (status = 1, the
     * profile-master join) that live in LmsQueryScope; every other LMS table is
     * simply tenant-scoped.
     */
    private function lmsScoped(string $table)
    {
        switch ($table) {
            case 'hrms_departments':
                return $this->lmsDepartments();
            case 'tbluser':
                return $this->lmsPeople();
            case 'tblstudent':
                return $this->lmsStudents();
            default:
                return DB::table($table)->where($table.'.sub_institute_id', $this->tenantId);
        }
    }

    private function brainCount(string $table): int
    {
        if (! $this->has($table) || ! SchemaCache::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('tenant_id', $this->tenantId)->count();
    }

    /** @return array<int, array{label: string, value: int}> */
    /**
     * A "how many of each" chart over one column.
     *
     * The population is never re-derived here. An LMS table goes through
     * LmsQueryScope, so a chart of staff by gender adds up to exactly the staff
     * headcount printed above it — and, since lmsPeople() joins
     * tbluserprofilemaster, the grouped column has to be table-qualified or
     * MySQL rejects `gender` / `status` / `sub_institute_id` as ambiguous.
     */
    private function breakdown(string $table, string $column, string $tenantColumn, int $limit = 10, bool $activePopulation = false): array
    {
        if (! $this->has($table) || ! SchemaCache::hasColumn($table, $column)) {
            return [];
        }

        $qualified = $table.'.'.$column;

        if ($tenantColumn === 'sub_institute_id') {
            $q = $this->lmsScoped($table);
        } else {
            $q = DB::table($table)->where($tenantColumn, $this->tenantId);
        }

        return $q->select($qualified.' as label', DB::raw('COUNT(*) as value'))
            ->groupBy($qualified)->orderByDesc('value')->limit($limit)->get()
            ->map(fn ($r) => [
                'label' => \App\Http\Controllers\Brain\BrainIntelligenceController::humaniseLabel((string) ($r->label ?? '')),
                'code' => (string) ($r->label ?? ''),
                'value' => (int) $r->value,
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int}> */
    private function brainBreakdown(string $table, string $column, int $limit = 10): array
    {
        return $this->breakdown($table, $column, 'tenant_id', $limit);
    }

    /**
     * The area of school life a source table belongs to.
     *
     * Signals record where they were read from — `vivek_erp.attendance_student`
     * — because evidence has to be traceable. That is the right thing to STORE
     * and the wrong thing to put on a chart axis, so it is translated once here.
     */
    private const SOURCE_AREAS = [
        'hrms_departments' => 'Departments',
        'tbluser' => 'Staff',
        'tbluserprofilemaster' => 'Staff',
        'tblstudent' => 'Students',
        'attendance_student' => 'Student attendance',
        'attendance' => 'Staff attendance',
        'staff_attendance' => 'Staff attendance',
        'result_marks' => 'Marks and results',
        'result_create_exam' => 'Examinations',
        'homework' => 'Homework',
        'fees_collect' => 'Fees',
        'fees_master' => 'Fees',
        'standard' => 'Classes',
        'section' => 'Sections',
        'subject' => 'Subjects',
        'class_teacher' => 'Class allocation',
        's_users_skills' => 'Capabilities',
        'competency' => 'Capabilities',
        'complaint' => 'Complaints',
    ];

    /**
     * Re-group a source breakdown by area, summing the tables that share one.
     *
     * @param  array<int, array{label: string, value: int, code?: string}>  $rows
     * @return array<int, array{label: string, value: int}>
     */
    private function byArea(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $raw = (string) ($row['code'] ?? $row['label']);
            $table = str_contains($raw, '.') ? substr($raw, strrpos($raw, '.') + 1) : $raw;
            $label = self::SOURCE_AREAS[$table]
                ?? \App\Http\Controllers\Brain\BrainIntelligenceController::humaniseLabel($table);

            $totals[$label] = ($totals[$label] ?? 0) + (int) $row['value'];
        }

        arsort($totals);

        return array_map(
            fn ($label, $value) => ['label' => $label, 'value' => $value],
            array_keys($totals),
            array_values($totals)
        );
    }

    /**
     * Replace stored codes with the words they stand for.
     *
     * 'P'/'A'/'L' and 0/1 are what the LMS writes; a chart legend reading "A: 130"
     * asks the reader to know the schema. The mapping is applied here rather than
     * in the front end so every consumer of this API shows the same labels.
     *
     * @param  array<int, array{label: string, value: int}>  $rows
     * @param  array<string, string>  $labels
     * @return array<int, array{label: string, value: int}>
     */
    private function labelled(array $rows, array $labels): array
    {
        return array_map(function ($row) use ($labels) {
            $row['code'] = $row['label'];
            $row['label'] = $labels[$row['label']] ?? $row['label'];

            return $row;
        }, $rows);
    }

    /** @return array<string, mixed> */
    private function tile(string $key, string $label, int $value, string $table): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'table' => $table, 'available' => $this->has($table)];
    }
}
