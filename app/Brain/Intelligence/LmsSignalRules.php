<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\LmsQueryScope;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The Brain's rule set over the LMS's own vivek_erp tables.
 *
 * SHAPE IS TAKEN FROM hp-enterprise-brain/app/Domain/Signals/OperationalSignalRules.php:
 * every rule is a closure returning ['created' => bool, ...], every rule writes
 * through SignalWriter, and rules are attached BY DATA rather than by name —
 * rulesFor() asks which tables this tenant actually has rows in and returns only
 * the rules those rows can answer. An installation with no fee module simply
 * never gets the fee rules; nothing is faked to fill the screen.
 *
 * WHAT IS DIFFERENT FROM THE REFERENCE, AND WHY. The reference reads an
 * `operational_records` staging table populated by spreadsheet import. There is
 * no staging table here and there should not be one: the LMS's own tables ARE
 * the system of record, and copying 3,438 students into a Brain-side mirror to
 * ask a question about them would create a second source of truth that goes
 * stale between runs. So every rule below queries vivek_erp directly, scoped by
 * sub_institute_id, with the aggregate pushed into SQL rather than pulled into
 * PHP.
 *
 * THRESHOLDS are proportions, not absolute counts, so a rule means the same
 * thing for an institute of 120 staff and one of 12,000. They sit in
 * config('brain.thresholds') so a deployment can tune them without a code change.
 *
 * EVERY RULE SAMPLES REAL ROWS AS EVIDENCE. A signal that says "607 departments
 * have no head" is a counter; the evidence rows say WHICH ones, and that is what
 * makes the recommendation actionable rather than a restatement.
 */
final class LmsSignalRules
{
    use LmsQueryScope;

    /** How many real rows are captured as evidence behind one signal. */
    private const EVIDENCE_SAMPLE = 8;

    private ?TrendAnalyzer $trends = null;

    public function __construct(
        private readonly string $tenantId,
        private readonly SignalWriter $writer,
        ?string $syear = null,
    ) {
        $this->syear = $syear;
    }

    /**
     * The rules this tenant's data can actually answer.
     *
     * @return array<string, callable(): array>
     */
    public function applicable(): array
    {
        $rules = [];

        if ($this->has('hrms_departments')) {
            $rules['department_without_head'] = fn () => $this->departmentWithoutHead();
            $rules['department_without_description'] = fn () => $this->departmentWithoutDescription();
            $rules['department_inactive_with_staff'] = fn () => $this->departmentInactiveWithStaff();

            if ($this->has('tbluser')) {
                $rules['department_without_staff'] = fn () => $this->departmentWithoutStaff();
                $rules['staff_concentration'] = fn () => $this->staffConcentration();
            }
        }

        if ($this->has('tbluser')) {
            $rules['person_without_department'] = fn () => $this->personWithoutDepartment();
            $rules['person_without_job_title'] = fn () => $this->personWithoutJobTitle();
            $rules['person_without_reporting_manager'] = fn () => $this->personWithoutReportingManager();
            $rules['person_never_logged_in'] = fn () => $this->personNeverLoggedIn();
            $rules['person_incomplete_contact'] = fn () => $this->personIncompleteContact();
            $rules['person_inactive_still_assigned'] = fn () => $this->personInactiveStillAssigned();
        }

        if ($this->has('tblstudent')) {
            $rules['student_missing_contact'] = fn () => $this->studentMissingContact();
            $rules['student_missing_identity'] = fn () => $this->studentMissingIdentity();
            $rules['student_missing_dob'] = fn () => $this->studentMissingDob();
            $rules['student_missing_enrollment_no'] = fn () => $this->studentMissingEnrollmentNo();

            if ($this->has('attendance_student')) {
                $rules['attendance_coverage_gap'] = fn () => $this->attendanceCoverageGap();
                $rules['student_absence_rate'] = fn () => $this->studentAbsenceRate();
                $rules['student_chronic_absentee'] = fn () => $this->studentChronicAbsentee();
                // Change over time, not just level: what MOVED since last month.
                $rules['attendance_decline'] = fn () => $this->attendanceDecline();
                $rules['class_below_attendance_baseline'] = fn () => $this->classBelowAttendanceBaseline();
            }

            if ($this->has('result_marks')) {
                $rules['result_coverage_gap'] = fn () => $this->resultCoverageGap();
                $rules['result_low_performance'] = fn () => $this->resultLowPerformance();
            }

            if ($this->has('fees_collect')) {
                $rules['fee_collection_coverage'] = fn () => $this->feeCollectionCoverage();
                $rules['fee_collection_decline'] = fn () => $this->feeCollectionDecline();
            }
        }

        if ($this->has('hrms_attendances')) {
            $rules['staff_attendance_open_punch'] = fn () => $this->staffAttendanceOpenPunch();
        }

        if ($this->has('homework')) {
            $rules['homework_non_submission'] = fn () => $this->homeworkNonSubmission();
            $rules['homework_decline'] = fn () => $this->homeworkDecline();
            $rules['subject_below_homework_baseline'] = fn () => $this->subjectBelowHomeworkBaseline();
        }

        if ($this->has('complaint')) {
            $rules['complaint_unresolved'] = fn () => $this->complaintUnresolved();
        }

        if ($this->has('hpbrain_capabilities')) {
            $rules['capability_unassigned'] = fn () => $this->capabilityUnassigned();
        }

        return $rules;
    }

    /* ==================================================== organization design */

    private function departmentWithoutHead(): array
    {
        $activeDept = fn () => $this->departments()
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('hrms_departments.status', 1));
        $total = (int) $activeDept()->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $missing = (int) $activeDept()->where(fn ($q) => $q->whereNull('hrms_departments.head_user_id')->orWhere('hrms_departments.head_user_id', 0))->count();
        $share = $missing / $total;
        if ($share < $this->threshold('department_without_head', 0.20)) {
            return $this->skip('below_threshold');
        }

        $joinClause = SchemaCache::hasTable('tbluserprofilemaster')
            ? function ($join) {
                $join->on('u.department_id', '=', 'hrms_departments.id')
                    ->where('u.sub_institute_id', '=', $this->tenantId)
                    ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id');
            }
            : function ($join) {
                $join->on('u.department_id', '=', 'hrms_departments.id')
                    ->where('u.sub_institute_id', '=', $this->tenantId);
            };

        $samples = $activeDept()
            ->where(fn ($q) => $q->whereNull('hrms_departments.head_user_id')->orWhere('hrms_departments.head_user_id', 0))
            ->leftJoin('tbluser as u', $joinClause)
            ->select('hrms_departments.id', 'hrms_departments.department', DB::raw('COUNT(u.id) as staff_count'))
            ->groupBy('hrms_departments.id', 'hrms_departments.department')
            ->orderByDesc('staff_count')
            ->limit(self::EVIDENCE_SAMPLE)
            ->get();

        $evidence = [];
        foreach ($samples as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.hrms_departments',
                'recordId' => (string) $row->id,
                'department' => (string) $row->department,
                'staffCount' => (int) $row->staff_count,
                'issue' => 'department has no head_user_id',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.hrms_departments',
            'classification' => 'organization_ownership',
            'severity' => $share >= 0.60 ? 'high' : 'medium',
            'priority' => $share >= 0.60 ? 'high' : 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'OrganizationUnit',
            'metadata' => [
                'rule' => 'department_without_head',
                'title' => sprintf('%d of %d departments have no accountable head', $missing, $total),
                'affectedCount' => $missing,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'departments',
            ],
        ], $evidence);
    }

    private function departmentWithoutDescription(): array
    {
        $activeDept = fn () => $this->departments()
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('hrms_departments.status', 1));
        $total = (int) $activeDept()->count();
        if ($total === 0 || ! SchemaCache::hasColumn('hrms_departments', 'description')) {
            return $this->skip('no_data');
        }

        $missing = (int) $activeDept()->where(fn ($q) => $q->whereNull('hrms_departments.description')->orWhere('hrms_departments.description', ''))->count();
        $share = $missing / $total;
        if ($share < $this->threshold('department_without_description', 0.30)) {
            return $this->skip('below_threshold');
        }

        $evidence = [];
        foreach ($activeDept()->where(fn ($q) => $q->whereNull('hrms_departments.description')->orWhere('hrms_departments.description', ''))
            ->orderBy('department')->limit(self::EVIDENCE_SAMPLE)->get(['id', 'department']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.hrms_departments',
                'recordId' => (string) $row->id,
                'department' => (string) $row->department,
                'issue' => 'department has no description or remit',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.hrms_departments',
            'classification' => 'organization_definition',
            'severity' => 'low',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'OrganizationUnit',
            'metadata' => [
                'rule' => 'department_without_description',
                'title' => sprintf('%d of %d departments have no written remit', $missing, $total),
                'affectedCount' => $missing,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'departments',
            ],
        ], $evidence);
    }

    private function departmentWithoutStaff(): array
    {
        $total = $this->departments()->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('hrms_departments.status', 1))->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $occupied = (int) DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', $this->tenantId)
            ->whereNotNull('u.department_id')->where('u.department_id', '>', 0)
            ->distinct()->count('u.department_id');

        $empty = max(0, $total - $occupied);
        $share = $empty / $total;
        if ($share < $this->threshold('department_without_staff', 0.50)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.hrms_departments+tbluser',
            'issue' => 'department tree is far larger than the staff roster occupying it',
            'departments' => $total,
            'departmentsWithStaff' => $occupied,
            'departmentsEmpty' => $empty,
        ])];

        foreach ($this->departments()
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('hrms_departments.status', 1))
            ->whereNotIn('hrms_departments.id', DB::table('tbluser as u')
                ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
                ->where('u.sub_institute_id', $this->tenantId)
                ->whereNotNull('u.department_id')->where('u.department_id', '>', 0)->distinct()->pluck('u.department_id'))
            ->orderBy('department')->limit(self::EVIDENCE_SAMPLE)->get(['id', 'department']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.hrms_departments',
                'recordId' => (string) $row->id,
                'department' => (string) $row->department,
                'issue' => 'department carries no staff',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.hrms_departments',
            'classification' => 'organization_structure',
            'severity' => $share >= 0.90 ? 'medium' : 'low',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'OrganizationUnit',
            'metadata' => [
                'rule' => 'department_without_staff',
                'title' => sprintf('%d of %d departments carry no staff', $empty, $total),
                'affectedCount' => $empty,
                'totalCount' => $total,
                'share' => round($share, 4),
                'departmentsWithStaff' => $occupied,
                'unit' => 'departments',
            ],
        ], $evidence);
    }

    private function departmentInactiveWithStaff(): array
    {
        if (! SchemaCache::hasColumn('hrms_departments', 'status')) {
            return $this->skip('no_data');
        }

        $rows = $this->departments()->where('hrms_departments.status', '!=', 1)
            ->join('tbluser as u', function ($join) {
                $join->on('u.department_id', '=', 'hrms_departments.id')->where('u.sub_institute_id', '=', $this->tenantId);
            })
            ->select('hrms_departments.id', 'hrms_departments.department', DB::raw('COUNT(u.id) as staff_count'))
            ->groupBy('hrms_departments.id', 'hrms_departments.department')
            ->having('staff_count', '>', 0)
            ->orderByDesc('staff_count')
            ->limit(50)->get();

        if ($rows->isEmpty()) {
            return $this->skip('no_match');
        }

        $evidence = [];
        foreach ($rows->take(self::EVIDENCE_SAMPLE) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.hrms_departments',
                'recordId' => (string) $row->id,
                'department' => (string) $row->department,
                'staffCount' => (int) $row->staff_count,
                'issue' => 'deactivated department still holds staff',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.hrms_departments',
            'classification' => 'organization_lifecycle',
            'severity' => 'high',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'OrganizationUnit',
            'metadata' => [
                'rule' => 'department_inactive_with_staff',
                'title' => sprintf('%d deactivated departments still hold staff', $rows->count()),
                'affectedCount' => $rows->count(),
                'staffAffected' => (int) $rows->sum('staff_count'),
                'unit' => 'departments',
            ],
        ], $evidence);
    }

    private function staffConcentration(): array
    {
        if (! SchemaCache::hasTable('hrms_departments')) {
            return $this->skip('no_data');
        }

        // Grouped THROUGH the department row. tbluser.department_id is not a
        // tenant-scoped foreign key in this schema, so grouping off tbluser
        // alone concentrates this school's staff into another school's
        // departments and then names them from that other school's rows.
        $rows = $this->lmsPeople()
            ->join('hrms_departments as d', 'd.id', '=', 'tbluser.department_id')
            ->where('d.sub_institute_id', $this->tenantId)
            ->when(SchemaCache::hasColumn('hrms_departments', 'status'), fn ($q) => $q->where('d.status', 1))
            ->select('tbluser.department_id', DB::raw('COUNT(*) as headcount'))
            ->groupBy('tbluser.department_id')->orderByDesc('headcount')->limit(20)->get();

        $assigned = (int) $rows->sum('headcount');
        if ($assigned === 0 || $rows->count() < 2) {
            return $this->skip('no_data');
        }

        $topShare = ((int) $rows->first()->headcount) / $assigned;
        if ($topShare < $this->threshold('staff_concentration', 0.35)) {
            return $this->skip('below_threshold');
        }

        $names = $this->lmsDepartments()
            ->whereIn('hrms_departments.id', $rows->pluck('department_id'))
            ->pluck('department', 'id');

        $evidence = [];
        foreach ($rows->take(self::EVIDENCE_SAMPLE) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.tbluser',
                'recordId' => (string) $row->department_id,
                'department' => (string) ($names[$row->department_id] ?? ('Department '.$row->department_id)),
                'headcount' => (int) $row->headcount,
                'shareOfAssignedStaff' => round(((int) $row->headcount) / $assigned, 4),
                'issue' => 'departmental share of the assigned staff roster',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.tbluser',
            'classification' => 'span_of_control',
            'severity' => $topShare >= 0.60 ? 'medium' : 'low',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'OrganizationUnit',
            'relatedEntityId' => (string) $rows->first()->department_id,
            'departmentId' => (string) $rows->first()->department_id,
            'metadata' => [
                'rule' => 'staff_concentration',
                'title' => sprintf(
                    '%s holds %d%% of all assigned staff',
                    (string) ($names[$rows->first()->department_id] ?? 'One department'),
                    (int) round($topShare * 100)
                ),
                'affectedCount' => (int) $rows->first()->headcount,
                'totalCount' => $assigned,
                'share' => round($topShare, 4),
                'unit' => 'staff',
            ],
        ], $evidence);
    }

    /* ================================================================= people */

    private function personWithoutDepartment(): array
    {
        return $this->peopleFieldRule(
            'person_without_department',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('tbluser.department_id')->orWhere('tbluser.department_id', 0)),
            'staff member has no department',
            'organization_ownership',
            0.005,
            fn (float $share) => $share >= 0.10 ? 'high' : 'medium',
            'have no department assigned'
        );
    }

    private function personWithoutJobTitle(): array
    {
        if (! SchemaCache::hasColumn('tbluser', 'jobtitle_id')) {
            return $this->skip('no_data');
        }

        return $this->peopleFieldRule(
            'person_without_job_title',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('tbluser.jobtitle_id')->orWhere('tbluser.jobtitle_id', 0)),
            'staff member has no job title',
            'role_definition',
            0.20,
            fn (float $share) => $share >= 0.90 ? 'high' : 'medium',
            'have no job title, which blocks every capability and progression view'
        );
    }

    private function personWithoutReportingManager(): array
    {
        if (! SchemaCache::hasColumn('tbluser', 'reporting_manager_id')) {
            return $this->skip('no_data');
        }

        return $this->peopleFieldRule(
            'person_without_reporting_manager',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('tbluser.reporting_manager_id')->orWhere('tbluser.reporting_manager_id', 0)),
            'staff member has no reporting manager',
            'organization_ownership',
            0.20,
            fn (float $share) => $share >= 0.90 ? 'high' : 'medium',
            'have no reporting manager, so there is no accountability chain'
        );
    }

    private function personNeverLoggedIn(): array
    {
        if (! SchemaCache::hasColumn('tbluser', 'last_login')) {
            return $this->skip('no_data');
        }

        return $this->peopleFieldRule(
            'person_never_logged_in',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('tbluser.last_login')->orWhere('tbluser.last_login', '')),
            'staff account has never been signed in to',
            'adoption',
            0.30,
            fn (float $share) => $share >= 0.90 ? 'high' : 'medium',
            'have never signed in'
        );
    }

    private function personIncompleteContact(): array
    {
        return $this->peopleFieldRule(
            'person_incomplete_contact',
            fn ($q) => $q->where(function ($i) {
                $i->whereNull('tbluser.email')->orWhere('tbluser.email', '')
                    ->orWhereNull('tbluser.mobile')->orWhere('tbluser.mobile', '');
            }),
            'staff record has no reachable email or mobile',
            'data_quality',
            0.02,
            fn (float $share) => $share >= 0.20 ? 'medium' : 'low',
            'have no reachable email or mobile'
        );
    }

    private function personInactiveStillAssigned(): array
    {
        if (! SchemaCache::hasColumn('tbluser', 'status')) {
            return $this->skip('no_data');
        }

        return $this->peopleFieldRule(
            'person_inactive_still_assigned',
            fn ($q) => $q->where('tbluser.status', 0)->whereNotNull('tbluser.department_id')->where('tbluser.department_id', '>', 0),
            'inactive staff member still counts towards a department headcount',
            'lifecycle',
            0.005,
            fn (float $share) => $share >= 0.05 ? 'medium' : 'low',
            'are inactive but still posted to a department'
        );
    }

    /**
     * The shared body of every "N of M staff records lack X" rule.
     *
     * Six rules with one shape: count the roster, count the subset, compare the
     * proportion against a threshold, sample real rows as evidence. Writing that
     * out six times would be six chances for the tenant scope or the evidence
     * sample to drift apart between them.
     */
    private function peopleFieldRule(
        string $ruleKey,
        callable $filter,
        string $issue,
        string $classification,
        float $defaultThreshold,
        callable $severity,
        string $phrase,
    ): array {
        $total = $this->lmsCount('tbluser');
        if (! SchemaCache::hasTable('tbluserprofilemaster')) {
            $total = (int) $this->lmsPeople()->count();
        }
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $matching = $filter($this->lmsPeople())->count();
        if ($matching === 0) {
            return $this->skip('no_match');
        }

        $share = $matching / $total;
        if ($share < $this->threshold($ruleKey, $defaultThreshold)) {
            return $this->skip('below_threshold');
        }

        $columns = array_values(array_map(
            fn ($c) => 'tbluser.'.$c,
            array_filter(
                ['id', 'first_name', 'last_name', 'email', 'mobile', 'employee_no', 'department_id', 'status'],
                fn ($c) => SchemaCache::hasColumn('tbluser', $c)
            )
        ));

        $evidence = [];
        foreach ($filter($this->lmsPeople())
            ->orderBy('tbluser.id')->limit(self::EVIDENCE_SAMPLE)->get($columns) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.tbluser',
                'recordId' => (string) $row->id,
                'person' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ('User '.$row->id),
                'employeeNo' => (string) ($row->employee_no ?? ''),
                'departmentId' => $row->department_id ?? null,
                'issue' => $issue,
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.tbluser',
            'classification' => $classification,
            'severity' => $severity($share),
            'priority' => $share >= 0.50 ? 'high' : 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Person',
            'metadata' => [
                'rule' => $ruleKey,
                'title' => sprintf('%d of %d staff %s', $matching, $total, $phrase),
                'affectedCount' => $matching,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'staff',
            ],
        ], $evidence);
    }

    /* =============================================================== students */

    private function studentMissingContact(): array
    {
        return $this->studentFieldRule(
            'student_missing_contact',
            fn ($q) => $q->where(function ($i) {
                $i->where(fn ($x) => $x->whereNull('mobile')->orWhere('mobile', ''))
                    ->where(fn ($x) => $x->whereNull('student_mobile')->orWhere('student_mobile', ''))
                    ->where(fn ($x) => $x->whereNull('mother_mobile')->orWhere('mother_mobile', ''));
            }),
            'student has no contactable number on any guardian field',
            'data_quality',
            0.001,
            'have no contactable phone number'
        );
    }

    private function studentMissingIdentity(): array
    {
        if (! SchemaCache::hasColumn('tblstudent', 'adharnumber')) {
            return $this->skip('no_data');
        }

        return $this->studentFieldRule(
            'student_missing_identity',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('adharnumber')->orWhere('adharnumber', '')),
            'student record carries no statutory identifier',
            'compliance',
            0.20,
            'have no statutory identifier recorded'
        );
    }

    private function studentMissingDob(): array
    {
        return $this->studentFieldRule(
            'student_missing_dob',
            fn ($q) => $q->whereNull('dob'),
            'student has no date of birth',
            'data_quality',
            0.001,
            'have no date of birth'
        );
    }

    private function studentMissingEnrollmentNo(): array
    {
        return $this->studentFieldRule(
            'student_missing_enrollment_no',
            fn ($q) => $q->where(fn ($i) => $i->whereNull('enrollment_no')->orWhere('enrollment_no', '')),
            'student has no enrollment number',
            'data_quality',
            0.001,
            'have no enrollment number'
        );
    }

    private function studentFieldRule(
        string $ruleKey,
        callable $filter,
        string $issue,
        string $classification,
        float $defaultThreshold,
        string $phrase,
    ): array {
        $base = fn () => $this->lmsStudents();
        $total = (int) $base()->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $matching = $filter($base())->count();
        if ($matching === 0) {
            return $this->skip('no_match');
        }

        $share = $matching / $total;
        if ($share < $this->threshold($ruleKey, $defaultThreshold)) {
            return $this->skip('below_threshold');
        }

        $evidence = [];
        foreach ($filter($base())
            ->orderBy('tblstudent.id')->limit(self::EVIDENCE_SAMPLE)
            ->get(['tblstudent.id', 'tblstudent.enrollment_no', 'tblstudent.first_name', 'tblstudent.last_name', 'tblstudent.admission_year']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.tblstudent',
                'recordId' => (string) $row->id,
                'student' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ('Student '.$row->id),
                'enrollmentNo' => (string) ($row->enrollment_no ?? ''),
                'admissionYear' => $row->admission_year ?? null,
                'issue' => $issue,
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.tblstudent',
            'classification' => $classification,
            'severity' => $share >= 0.50 ? 'high' : ($share >= 0.05 ? 'medium' : 'low'),
            'priority' => $share >= 0.50 ? 'high' : 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => $ruleKey,
                'title' => sprintf('%d of %d students %s', $matching, $total, $phrase),
                'affectedCount' => $matching,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'students',
            ],
        ], $evidence);
    }

    /* ============================================================= attendance */

    private function attendanceCoverageGap(): array
    {
        $students = SchemaCache::hasTable('tblstudent') && SchemaCache::hasColumn('tblstudent', 'status')
            ? (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->where('status', 1)->count()
            : (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->count();
        if ($students === 0) {
            return $this->skip('no_data');
        }

        $covered = (int) $this->lmsAttendance()->distinct()->count('student_id');

        $share = 1 - ($covered / $students);
        if ($share < $this->threshold('attendance_coverage_gap', 0.30)) {
            return $this->skip('below_threshold');
        }

        $range = $this->lmsAttendance()
            ->selectRaw('MIN(attendance_date) as first_date, MAX(attendance_date) as last_date, COUNT(*) as marks')->first();

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.attendance_student',
            'issue' => 'digital attendance covers a small fraction of the enrolled roll',
            'studentsEnrolled' => $students,
            'studentsWithAnyAttendance' => $covered,
            'attendanceMarks' => (int) ($range->marks ?? 0),
            'firstMarkedDate' => (string) ($range->first_date ?? ''),
            'lastMarkedDate' => (string) ($range->last_date ?? ''),
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.attendance_student',
            'classification' => 'process_adoption',
            'severity' => $share >= 0.90 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'attendance_coverage_gap',
                'title' => sprintf('Attendance is recorded for only %d of %d students', $covered, $students),
                'affectedCount' => $students - $covered,
                'totalCount' => $students,
                'share' => round($share, 4),
                'coveredCount' => $covered,
                'unit' => 'students',
            ],
        ], $evidence);
    }

    private function studentAbsenceRate(): array
    {
        $counts = $this->lmsAttendance()
            ->select('attendance_code', DB::raw('COUNT(*) as marks'))
            ->groupBy('attendance_code')->pluck('marks', 'attendance_code');

        $total = (int) $counts->sum();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        // 'A' absent, 'L' leave — both are non-attendance for this purpose, but
        // they are reported separately because the intervention differs.
        $absent = (int) ($counts['A'] ?? 0);
        $leave = (int) ($counts['L'] ?? 0);
        $share = ($absent + $leave) / $total;

        if ($share < $this->threshold('student_absence_rate', 0.08)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.attendance_student',
            'issue' => 'non-attendance rate across all recorded marks',
            'presentMarks' => (int) ($counts['P'] ?? 0),
            'absentMarks' => $absent,
            'leaveMarks' => $leave,
            'totalMarks' => $total,
            'absenceRate' => round($share, 4),
        ])];

        foreach ($this->lmsAttendance()->where('attendance_code', 'A')
            ->select('standard_id', 'section_id', DB::raw('COUNT(*) as absences'))
            ->groupBy('standard_id', 'section_id')->orderByDesc('absences')->limit(self::EVIDENCE_SAMPLE)->get() as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.attendance_student',
                'recordId' => $row->standard_id.'-'.$row->section_id,
                'standardId' => $row->standard_id,
                'sectionId' => $row->section_id,
                'absences' => (int) $row->absences,
                'issue' => 'absence concentration by class and section',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.attendance_student',
            'classification' => 'student_engagement',
            'severity' => $share >= 0.15 ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'student_absence_rate',
                'title' => sprintf('%.1f%% of recorded attendance marks are absence or leave', $share * 100),
                'affectedCount' => $absent + $leave,
                'totalCount' => $total,
                'share' => round($share, 4),
                'absentMarks' => $absent,
                'leaveMarks' => $leave,
                'unit' => 'attendance marks',
            ],
        ], $evidence);
    }

    private function studentChronicAbsentee(): array
    {
        $floor = (int) config('brain.thresholds.chronic_absence_marks', 5);

        $rows = $this->lmsAttendance()->where('attendance_code', 'A')
            ->select('student_id', DB::raw('COUNT(*) as absences'))
            ->groupBy('student_id')->having('absences', '>=', $floor)
            ->orderByDesc('absences')->limit(100)->get();

        if ($rows->isEmpty()) {
            return $this->skip('no_match');
        }

        $names = DB::table('tblstudent')->whereIn('id', $rows->pluck('student_id'))
            ->when(SchemaCache::hasColumn('tblstudent', 'status'), fn ($q) => $q->where('status', 1))
            ->select('id', 'first_name', 'last_name', 'enrollment_no')->get()->keyBy('id');

        $evidence = [];
        foreach ($rows->take(self::EVIDENCE_SAMPLE) as $row) {
            $student = $names[$row->student_id] ?? null;
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.attendance_student',
                'recordId' => (string) $row->student_id,
                'student' => $student ? trim($student->first_name.' '.$student->last_name) : ('Student '.$row->student_id),
                'enrollmentNo' => (string) ($student->enrollment_no ?? ''),
                'absences' => (int) $row->absences,
                'issue' => 'student has '.$row->absences.' recorded absences',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.attendance_student',
            'classification' => 'student_engagement',
            'severity' => 'high',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'relatedEntityId' => (string) $rows->first()->student_id,
            'metadata' => [
                'rule' => 'student_chronic_absentee',
                'title' => sprintf('%d students have %d or more recorded absences', $rows->count(), $floor),
                'affectedCount' => $rows->count(),
                'threshold' => $floor,
                'worstCount' => (int) $rows->first()->absences,
                'unit' => 'students',
            ],
        ], $evidence);
    }

    private function staffAttendanceOpenPunch(): array
    {
        $total = DB::table('hrms_attendances')->where('sub_institute_id', $this->tenantId)->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $open = DB::table('hrms_attendances')->where('sub_institute_id', $this->tenantId)
            ->whereNotNull('punchin_time')->whereNull('punchout_time')->count();

        if ($open === 0) {
            return $this->skip('no_match');
        }

        $share = $open / $total;
        if ($share < $this->threshold('staff_attendance_open_punch', 0.005)) {
            return $this->skip('below_threshold');
        }

        $evidence = [];
        foreach (DB::table('hrms_attendances as a')
            ->leftJoin('tbluser as u', 'u.id', '=', 'a.user_id')
            ->where('a.sub_institute_id', $this->tenantId)
            ->whereNotNull('a.punchin_time')->whereNull('a.punchout_time')
            ->orderByDesc('a.day')->limit(self::EVIDENCE_SAMPLE)
            ->get(['a.id', 'a.user_id', 'a.day', 'a.punchin_time', 'a.employee_no', 'u.first_name', 'u.last_name']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.hrms_attendances',
                'recordId' => (string) $row->id,
                'person' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ('User '.$row->user_id),
                'employeeNo' => (string) ($row->employee_no ?? ''),
                'day' => (string) $row->day,
                'punchIn' => (string) $row->punchin_time,
                'issue' => 'shift punched in but never punched out',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.hrms_attendances',
            'classification' => 'data_quality',
            'severity' => $share >= 0.05 ? 'medium' : 'low',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Person',
            'metadata' => [
                'rule' => 'staff_attendance_open_punch',
                'title' => sprintf('%d staff attendance records have no punch-out', $open),
                'affectedCount' => $open,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'attendance records',
            ],
        ], $evidence);
    }

    /* ============================================================== academics */

    private function resultCoverageGap(): array
    {
        $students = SchemaCache::hasTable('tblstudent') && SchemaCache::hasColumn('tblstudent', 'status')
            ? (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->where('status', 1)->count()
            : (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->count();
        if ($students === 0) {
            return $this->skip('no_data');
        }

        $graded = (int) $this->lmsMarks()->distinct()->count('student_id');
        $share = 1 - ($graded / $students);
        if ($share < $this->threshold('result_coverage_gap', 0.30)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.result_marks',
            'issue' => 'marks are recorded for a small fraction of the enrolled roll',
            'studentsEnrolled' => $students,
            'studentsWithMarks' => $graded,
            'markRows' => $this->lmsMarks()->count(),
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.result_marks',
            'classification' => 'process_adoption',
            'severity' => $share >= 0.90 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'result_coverage_gap',
                'title' => sprintf('Marks are recorded for only %d of %d students', $graded, $students),
                'affectedCount' => $students - $graded,
                'totalCount' => $students,
                'share' => round($share, 4),
                'coveredCount' => $graded,
                'unit' => 'students',
            ],
        ], $evidence);
    }

    private function resultLowPerformance(): array
    {
        $stats = $this->lmsMarks()
            ->selectRaw('COUNT(*) as rows_count, AVG(per) as mean_pct, MIN(per) as min_pct, MAX(per) as max_pct')->first();

        $rows = (int) ($stats->rows_count ?? 0);
        if ($rows === 0) {
            return $this->skip('no_data');
        }

        $mean = (float) ($stats->mean_pct ?? 0);
        $floor = (float) config('brain.thresholds.pass_percentage', 40.0);
        if ($mean >= $floor) {
            return $this->skip('above_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.result_marks',
            'issue' => 'mean recorded score sits below the passing band',
            'meanPercentage' => round($mean, 2),
            'minPercentage' => round((float) $stats->min_pct, 2),
            'maxPercentage' => round((float) $stats->max_pct, 2),
            'markRows' => $rows,
            'passBand' => $floor,
        ])];

        foreach ($this->lmsMarks()
            ->where('per', '<', $floor)->orderBy('per')->limit(self::EVIDENCE_SAMPLE)
            ->get(['id', 'student_id', 'subject_name', 'exam_title', 'per', 'grade']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.result_marks',
                'recordId' => (string) $row->id,
                'studentId' => (string) $row->student_id,
                'subject' => (string) ($row->subject_name ?? ''),
                'exam' => (string) ($row->exam_title ?? ''),
                'percentage' => (float) $row->per,
                'grade' => (string) ($row->grade ?? ''),
                'issue' => 'recorded score below the passing band',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.result_marks',
            'classification' => 'academic_performance',
            'severity' => $mean < ($floor / 2) ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'result_low_performance',
                'title' => sprintf('Mean recorded score is %.1f%%, below the %.0f%% pass band', $mean, $floor),
                'affectedCount' => $this->lmsMarks()->where('per', '<', $floor)->count(),
                'totalCount' => $rows,
                'meanPercentage' => round($mean, 2),
                'passBand' => $floor,
                'unit' => 'mark records',
            ],
        ], $evidence);
    }

    /* =============================================================== homework */

    private function homeworkNonSubmission(): array
    {
        $total = $this->lmsHomework()->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $outstanding = $this->lmsHomework()
            ->where(fn ($q) => $q->where('completion_status', '!=', 'Y')->orWhereNull('completion_status'))->count();

        if ($outstanding === 0) {
            return $this->skip('no_match');
        }

        $share = $outstanding / $total;
        if ($share < $this->threshold('homework_non_submission', 0.10)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.homework',
            'issue' => 'homework assignments not marked complete',
            'outstanding' => $outstanding,
            'total' => $total,
            'nonSubmissionRate' => round($share, 4),
        ])];

        foreach ($this->lmsHomework()
            ->where(fn ($q) => $q->where('completion_status', '!=', 'Y')->orWhereNull('completion_status'))
            ->select('subject_id', DB::raw('COUNT(*) as outstanding'))
            ->groupBy('subject_id')->orderByDesc('outstanding')->limit(self::EVIDENCE_SAMPLE)->get() as $row) {
            $subject = DB::table('subject')->where('id', $row->subject_id)->value('subject_name');
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.homework',
                'recordId' => (string) $row->subject_id,
                'subject' => (string) ($subject ?? ('Subject '.$row->subject_id)),
                'outstanding' => (int) $row->outstanding,
                'issue' => 'outstanding homework concentrated in this subject',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.homework',
            'classification' => 'student_engagement',
            'severity' => $share >= 0.30 ? 'medium' : 'low',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'homework_non_submission',
                'title' => sprintf('%d of %d homework assignments are not marked complete', $outstanding, $total),
                'affectedCount' => $outstanding,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'assignments',
            ],
        ], $evidence);
    }

    /* =================================================================== fees */

    private function feeCollectionCoverage(): array
    {
        $students = SchemaCache::hasTable('tblstudent') && SchemaCache::hasColumn('tblstudent', 'status')
            ? (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->where('status', 1)->count()
            : (int) DB::table('tblstudent')->where('sub_institute_id', $this->tenantId)->count();
        if ($students === 0) {
            return $this->skip('no_data');
        }

        $paying = (int) $this->lmsFees()
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', '!=', 'Y'))
            ->distinct()->count('student_id');

        $share = 1 - ($paying / $students);
        if ($share < $this->threshold('fee_collection_coverage', 0.30)) {
            return $this->skip('below_threshold');
        }

        $totals = $this->lmsFees()
            ->selectRaw('COUNT(*) as receipts, SUM(amount) as collected, SUM(fine) as fines, SUM(fees_discount) as discounts')->first();

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect',
            'issue' => 'fee receipts exist for a small fraction of the enrolled roll',
            'studentsEnrolled' => $students,
            'studentsWithReceipts' => $paying,
            'receipts' => (int) ($totals->receipts ?? 0),
            'collectedAmount' => (float) ($totals->collected ?? 0),
            'fines' => (float) ($totals->fines ?? 0),
            'discounts' => (float) ($totals->discounts ?? 0),
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'process_adoption',
            'severity' => $share >= 0.90 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'fee_collection_coverage',
                'title' => sprintf('Fee receipts exist for only %d of %d students', $paying, $students),
                'affectedCount' => $students - $paying,
                'totalCount' => $students,
                'share' => round($share, 4),
                'coveredCount' => $paying,
                'collectedAmount' => (float) ($totals->collected ?? 0),
                'unit' => 'students',
            ],
        ], $evidence);
    }

    /* ============================================================= complaints */

    private function complaintUnresolved(): array
    {
        // This table is upper-cased in the LMS schema, unlike its neighbours.
        $total = DB::table('complaint')->where('SUB_INSTITUTE_ID', $this->tenantId)->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $open = DB::table('complaint')->where('SUB_INSTITUTE_ID', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('COMPLAINT_SOLUTION')->orWhere('COMPLAINT_SOLUTION', ''))->count();

        if ($open === 0) {
            return $this->skip('no_match');
        }

        $share = $open / $total;
        if ($share < $this->threshold('complaint_unresolved', 0.10)) {
            return $this->skip('below_threshold');
        }

        $evidence = [];
        foreach (DB::table('complaint')->where('SUB_INSTITUTE_ID', $this->tenantId)
            ->where(fn ($q) => $q->whereNull('COMPLAINT_SOLUTION')->orWhere('COMPLAINT_SOLUTION', ''))
            ->orderByDesc('DATE')->limit(self::EVIDENCE_SAMPLE)->get(['ID', 'TITLE', 'DATE', 'COMPLAINT_BY']) as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.complaint',
                'recordId' => (string) $row->ID,
                'title' => (string) $row->TITLE,
                'raisedOn' => (string) $row->DATE,
                'raisedBy' => (string) ($row->COMPLAINT_BY ?? ''),
                'issue' => 'complaint has no recorded resolution',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.complaint',
            'classification' => 'service_quality',
            'severity' => $share >= 0.40 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'metadata' => [
                'rule' => 'complaint_unresolved',
                'title' => sprintf('%d of %d complaints have no recorded resolution', $open, $total),
                'affectedCount' => $open,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'complaints',
            ],
        ], $evidence);
    }

    /* =========================================================== capabilities */

    private function capabilityUnassigned(): array
    {
        $total = DB::table('hpbrain_capabilities')->where('tenant_id', $this->tenantId)->count();
        if ($total === 0) {
            return $this->skip('no_data');
        }

        $assigned = SchemaCache::hasTable('hpbrain_capability_assignments')
            ? (int) DB::table('hpbrain_capability_assignments')->where('tenant_id', $this->tenantId)->distinct()->count('capability_id')
            : 0;

        $share = 1 - ($assigned / $total);
        if ($share < $this->threshold('capability_unassigned', 0.50)) {
            return $this->skip('below_threshold');
        }

        $evidence = [$this->writer->recordEvidence([
            'source' => 'hpbrain_capabilities',
            'issue' => 'capability library is not mapped to any department or person',
            'capabilities' => $total,
            'capabilitiesAssigned' => $assigned,
        ])];

        foreach (DB::table('hpbrain_capabilities')->where('tenant_id', $this->tenantId)
            ->select('category', DB::raw('COUNT(*) as capabilities'))
            ->groupBy('category')->orderByDesc('capabilities')->limit(self::EVIDENCE_SAMPLE)->get() as $row) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'hpbrain_capabilities',
                'recordId' => (string) $row->category,
                'category' => (string) ($row->category ?: 'Unspecified'),
                'capabilities' => (int) $row->capabilities,
                'issue' => 'unmapped capabilities by category',
            ]);
        }

        return $this->writer->raise([
            'source' => 'hpbrain_capabilities',
            'classification' => 'capability_activation',
            'severity' => 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Capability',
            'metadata' => [
                'rule' => 'capability_unassigned',
                'title' => sprintf('%d of %d capabilities are not assigned to any department or person', $total - $assigned, $total),
                'affectedCount' => $total - $assigned,
                'totalCount' => $total,
                'share' => round($share, 4),
                'unit' => 'capabilities',
            ],
        ], $evidence);
    }

    /* ============================================= change over time (trends) */

    /**
     * Attendance this month against last month.
     *
     * THE PARTIAL CURRENT MONTH IS EXCLUDED BY TrendAnalyzer, which matters more
     * than it sounds: comparing a half-recorded September against a complete
     * August manufactures a collapse that is only a shorter window, and that is
     * exactly the kind of false alarm that costs a system its credibility.
     */
    private function attendanceDecline(): array
    {
        $trend = $this->trends()->attendanceTrend();

        if (! $trend['available']) {
            return $this->skip('insufficient_history');
        }
        if ($trend['direction'] !== 'down' || ! $trend['material']) {
            return $this->skip('no_material_decline');
        }

        $drop = abs((float) $trend['changePoints']);
        $current = $trend['current'];
        $previous = $trend['previous'];

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.attendance_student',
            'issue' => 'attendance rate fell against the previous month',
            'currentPeriod' => $current['period'],
            'currentRate' => $current['rate'],
            'currentMarks' => $current['marks'],
            'previousPeriod' => $previous['period'],
            'previousRate' => $previous['rate'],
            'previousMarks' => $previous['marks'],
            'changePoints' => $trend['changePoints'],
        ])];

        // Name the classes carrying the fall, so the recommendation has somewhere
        // to point. A decline with no location is not actionable.
        foreach (array_slice($this->trends()->attendanceByClass()['classes'], 0, self::EVIDENCE_SAMPLE) as $class) {
            if ($class['gapPoints'] >= 0) {
                continue;
            }
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.attendance_student',
                'recordId' => $class['classId'],
                'class' => $class['className'],
                'attendanceRate' => $class['rate'],
                'gapVsBaseline' => $class['gapPoints'],
                'students' => $class['students'],
                'issue' => 'class attendance below the school baseline',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.attendance_student',
            'classification' => 'attendance_trend',
            'severity' => $drop >= 5 ? 'high' : 'medium',
            'priority' => $drop >= 5 ? 'high' : 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'attendance_decline',
                'title' => sprintf('Attendance fell %s points, from %s%% to %s%%', $this->fmt($drop), $this->fmt($previous['rate']), $this->fmt($current['rate'])),
                'currentRate' => $current['rate'],
                'previousRate' => $previous['rate'],
                'currentPeriod' => $current['period'],
                'previousPeriod' => $previous['period'],
                'changePoints' => $trend['changePoints'],
                'affectedCount' => $current['absent'],
                'totalCount' => $current['marks'],
                'unit' => 'attendance marks',
            ],
        ], $evidence);
    }

    /**
     * A class sitting well below the school's own attendance baseline.
     *
     * The baseline is this school's rate, not a national figure — a class at 85%
     * is a problem in a school averaging 92% and unremarkable in one averaging 84%.
     */
    private function classBelowAttendanceBaseline(): array
    {
        $byClass = $this->trends()->attendanceByClass();

        if ($byClass['classes'] === [] || $byClass['baseline'] <= 0) {
            return $this->skip('no_data');
        }

        $gapFloor = (float) config('brain.thresholds.class_attendance_gap_points', 4.0);
        $lagging = array_values(array_filter(
            $byClass['classes'],
            fn ($class) => $class['gapPoints'] <= -$gapFloor
        ));

        if ($lagging === []) {
            return $this->skip('no_class_below_baseline');
        }

        $worst = $lagging[0];

        $evidence = [];
        foreach (array_slice($lagging, 0, self::EVIDENCE_SAMPLE) as $class) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.attendance_student',
                'recordId' => $class['classId'],
                'class' => $class['className'],
                'attendanceRate' => $class['rate'],
                'schoolBaseline' => $byClass['baseline'],
                'gapPoints' => $class['gapPoints'],
                'students' => $class['students'],
                'absences' => $class['absent'],
                'issue' => 'class attendance below the school baseline',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.attendance_student',
            'classification' => 'attendance_trend',
            'severity' => abs($worst['gapPoints']) >= 8 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'relatedEntityId' => $worst['classId'],
            'metadata' => [
                'rule' => 'class_below_attendance_baseline',
                'title' => sprintf('%s attendance is %s points below the school baseline', $worst['className'], $this->fmt(abs($worst['gapPoints']))),
                'className' => $worst['className'],
                'currentRate' => $worst['rate'],
                'baseline' => $byClass['baseline'],
                'gapPoints' => $worst['gapPoints'],
                'students' => $worst['students'],
                'affectedCount' => count($lagging),
                'totalCount' => count($byClass['classes']),
                'unit' => 'classes',
            ],
        ], $evidence);
    }

    private function homeworkDecline(): array
    {
        $trend = $this->trends()->homeworkTrend();

        if (! $trend['available']) {
            return $this->skip('insufficient_history');
        }
        if ($trend['direction'] !== 'down' || ! $trend['material']) {
            return $this->skip('no_material_decline');
        }

        $current = $trend['current'];
        $previous = $trend['previous'];

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.homework',
            'issue' => 'homework submission rate fell against the previous month',
            'currentPeriod' => $current['period'],
            'currentRate' => $current['rate'],
            'previousPeriod' => $previous['period'],
            'previousRate' => $previous['rate'],
            'changePoints' => $trend['changePoints'],
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.homework',
            'classification' => 'engagement_trend',
            'severity' => abs((float) $trend['changePoints']) >= 8 ? 'high' : 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'metadata' => [
                'rule' => 'homework_decline',
                'title' => sprintf('Homework submission fell from %s%% to %s%%', $this->fmt($previous['rate']), $this->fmt($current['rate'])),
                'currentRate' => $current['rate'],
                'previousRate' => $previous['rate'],
                'currentPeriod' => $current['period'],
                'previousPeriod' => $previous['period'],
                'changePoints' => $trend['changePoints'],
                'affectedCount' => $current['total'] - $current['submitted'],
                'totalCount' => $current['total'],
                'unit' => 'assignments',
            ],
        ], $evidence);
    }

    private function subjectBelowHomeworkBaseline(): array
    {
        $bySubject = $this->trends()->homeworkBySubject();

        if ($bySubject['subjects'] === [] || $bySubject['baseline'] <= 0) {
            return $this->skip('no_data');
        }

        $gapFloor = (float) config('brain.thresholds.subject_homework_gap_points', 15.0);
        $lagging = array_values(array_filter(
            $bySubject['subjects'],
            fn ($subject) => $subject['gapPoints'] <= -$gapFloor
        ));

        if ($lagging === []) {
            return $this->skip('no_subject_below_baseline');
        }

        $worst = $lagging[0];

        $evidence = [];
        foreach (array_slice($lagging, 0, self::EVIDENCE_SAMPLE) as $subject) {
            $evidence[] = $this->writer->recordEvidence([
                'source' => 'vivek_erp.homework',
                'recordId' => $subject['subjectId'],
                'subject' => $subject['subject'],
                'submissionRate' => $subject['rate'],
                'schoolBaseline' => $bySubject['baseline'],
                'gapPoints' => $subject['gapPoints'],
                'outstanding' => $subject['outstanding'],
                'issue' => 'subject submission below the school baseline',
            ]);
        }

        return $this->writer->raise([
            'source' => 'vivek_erp.homework',
            'classification' => 'engagement_trend',
            'severity' => 'medium',
            'priority' => 'normal',
            'confidence' => 1.0,
            'relatedEntityType' => 'Student',
            'relatedEntityId' => $worst['subjectId'],
            'metadata' => [
                'rule' => 'subject_below_homework_baseline',
                'title' => sprintf('%s homework submission is %s points below the school baseline', $worst['subject'], $this->fmt(abs($worst['gapPoints']))),
                'subject' => $worst['subject'],
                'currentRate' => $worst['rate'],
                'baseline' => $bySubject['baseline'],
                'affectedCount' => $worst['outstanding'],
                'totalCount' => $worst['total'],
                'unit' => 'assignments',
            ],
        ], $evidence);
    }

    private function feeCollectionDecline(): array
    {
        $trend = $this->trends()->feeTrend();

        if (! $trend['available']) {
            return $this->skip('insufficient_history');
        }
        if ($trend['direction'] !== 'down' || ! $trend['material']) {
            return $this->skip('no_material_decline');
        }

        $current = $trend['current'];
        $previous = $trend['previous'];

        $evidence = [$this->writer->recordEvidence([
            'source' => 'vivek_erp.fees_collect',
            'issue' => 'fee collection fell against the previous month',
            'currentPeriod' => $current['period'],
            'currentCollected' => $current['collected'],
            'currentReceipts' => $current['receipts'],
            'previousPeriod' => $previous['period'],
            'previousCollected' => $previous['collected'],
            'previousReceipts' => $previous['receipts'],
            'changePercent' => $trend['changePercent'],
            'currency' => 'INR',
        ])];

        return $this->writer->raise([
            'source' => 'vivek_erp.fees_collect',
            'classification' => 'collection_trend',
            'severity' => abs((float) $trend['changePercent']) >= 25 ? 'high' : 'medium',
            'priority' => 'high',
            'confidence' => 1.0,
            'metadata' => [
                'rule' => 'fee_collection_decline',
                'title' => sprintf('Fee collection fell %s%% against the previous month', $this->fmt(abs((float) $trend['changePercent']))),
                'currentAmount' => $current['collected'],
                'previousAmount' => $previous['collected'],
                'currentPeriod' => $current['period'],
                'previousPeriod' => $previous['period'],
                'changePercent' => $trend['changePercent'],
                'affectedCount' => $current['receipts'],
                'unit' => 'receipts',
            ],
        ], $evidence);
    }

    /* ================================================================ helpers */

    /**
     * This tenant's live departments.
     *
     * EVERY COLUMN IS TABLE-QUALIFIED because two of the callers join tbluser,
     * which also has sub_institute_id and status. Unqualified, MySQL rejects the
     * join with "Column 'sub_institute_id' in where clause is ambiguous" — and
     * the rule that would have named the headless departments carrying the most
     * staff is exactly the one that joins.
     */
    private function departments()
    {
        $query = DB::table('hrms_departments')->where('hrms_departments.sub_institute_id', $this->tenantId);

        if (SchemaCache::hasColumn('hrms_departments', 'deleted_at')) {
            $query->whereNull('hrms_departments.deleted_at');
        }

        return $query;
    }

    private function has(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    /**
     * Shared trend analyzer.
     *
     * Held for the life of the rule set rather than rebuilt per rule: four rules
     * ask for the same monthly attendance aggregate, and against a remote
     * database that is four identical scans instead of one.
     */
    private function trends(): TrendAnalyzer
    {
        return $this->trends ??= new TrendAnalyzer($this->tenantId, $this->syear);
    }

    /** One decimal place, without a trailing ".0" on whole numbers. */
    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    private function threshold(string $ruleKey, float $default): float
    {
        return (float) config('brain.thresholds.'.$ruleKey, $default);
    }

    /** @return array{created: bool, refreshed: bool, signalId: null, reason: string} */
    private function skip(string $reason): array
    {
        return ['created' => false, 'refreshed' => false, 'signalId' => null, 'reason' => $reason];
    }
}
