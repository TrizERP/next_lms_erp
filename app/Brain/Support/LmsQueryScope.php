<?php

namespace App\Brain\Support;

use Illuminate\Support\Facades\DB;

/**
 * Centralised, LMS-faithful query builders for vivek_erp tables.
 *
 * Every Brain intelligence class used to issue its own ad-hoc `DB::table(...)`
 * against hrms_departments, tbluser and tblstudent — and each one silently
 * diverged from the LMS UI's business rules (Brain omitted `status = 1`,
 * Brain omitted the `tbluserprofilemaster` inner join, etc.).
 *
 * This trait is the SINGLE source of truth for how the Brain reads LMS data.
 * Every table that the LMS treats as "active population" must go through
 * these methods so the Brain and the LMS can never disagree on what "is
 * an active department / person / student" means.
 *
 * IT IS ALSO THE SINGLE PLACE THAT DECIDES WHAT IS YEAR-SCOPED. `$syear` is the
 * academic year the LMS header currently has selected. It is applied ONLY where
 * the LMS itself applies it:
 *
 *   YEAR-SCOPED   attendance_student.syear, homework.syear, fees_collect.syear,
 *                 class_teacher.syear, and marks via result_create_exam.syear
 *   NOT SCOPED    hrms_departments, tbluser/tbluserprofilemaster, tblstudent,
 *                 s_users_skills and the capability tables — master data the
 *                 LMS's own screens list whole (see lmsStudents() for why the
 *                 student register belongs in this group despite having a
 *                 year-stamped enrolment table beside it).
 *
 * Filtering the second group by year would empty screens the LMS shows in full,
 * which is why nothing here applies a year filter to a table that has no year.
 */
trait LmsQueryScope
{
    /**
     * The selected academic year, or null to read every year.
     *
     * Null is the honest default: a caller that has not resolved a year must
     * not silently get one year's slice while believing it has the whole roll.
     */
    protected ?string $syear = null;

    /** The year in effect, for callers that need to report or pass it on. */
    public function syear(): ?string
    {
        return $this->syear;
    }

    /**
     * Apply the selected year to a query — but only if that table actually
     * records one. A `where syear = ?` against a table without the column is a
     * fatal error; against a table the LMS never filters it is a silent lie.
     */
    protected function applyYear($query, string $table, ?string $alias = null)
    {
        if ($this->syear === null || ! SchemaCache::hasColumn($table, 'syear')) {
            return $query;
        }

        return $query->where(($alias ?: $table).'.syear', $this->syear);
    }

    /** Student attendance for the selected year. */
    protected function lmsAttendance(?string $alias = null)
    {
        $from = $alias ? "attendance_student as {$alias}" : 'attendance_student';
        $prefix = $alias ?: 'attendance_student';

        return $this->applyYear(
            DB::table($from)->where($prefix.'.sub_institute_id', $this->tenantId),
            'attendance_student',
            $alias
        );
    }

    /** Homework set/submitted in the selected year. */
    protected function lmsHomework(?string $alias = null)
    {
        $from = $alias ? "homework as {$alias}" : 'homework';
        $prefix = $alias ?: 'homework';

        return $this->applyYear(
            DB::table($from)->where($prefix.'.sub_institute_id', $this->tenantId),
            'homework',
            $alias
        );
    }

    /** Fee collection for the selected year. */
    protected function lmsFees(?string $alias = null)
    {
        $from = $alias ? "fees_collect as {$alias}" : 'fees_collect';
        $prefix = $alias ?: 'fees_collect';

        return $this->applyYear(
            DB::table($from)->where($prefix.'.sub_institute_id', $this->tenantId),
            'fees_collect',
            $alias
        );
    }

    /**
     * Marks for the selected year.
     *
     * `result_marks` carries no year of its own — the LMS reaches the year
     * through the exam the mark was recorded against
     * (`result_marks.exam_id` -> `result_create_exam.id`), exactly as
     * overall_mark_report_controller does. Without that table, or without a
     * selected year, the marks are read unscoped rather than dropped.
     *
     * EXISTS rather than a join, for the same reason as the student roll and
     * one more: `result_create_exam` also has a `points` column, so joining it
     * would make `points` — which half the callers select unqualified —
     * ambiguous, and MySQL would reject the query rather than misanswer it.
     */
    protected function lmsMarks(?string $alias = null)
    {
        $prefix = $alias ?: 'result_marks';
        $query = DB::table($alias ? "result_marks as {$alias}" : 'result_marks')
            ->where($prefix.'.sub_institute_id', $this->tenantId);

        if ($this->syear !== null
            && SchemaCache::hasTable('result_create_exam')
            && SchemaCache::hasColumn('result_create_exam', 'syear')) {
            $syear = $this->syear;
            $tenant = $this->tenantId;
            $query->whereExists(function ($sub) use ($prefix, $syear, $tenant) {
                $sub->select(DB::raw(1))
                    ->from('result_create_exam')
                    ->whereColumn('result_create_exam.id', $prefix.'.exam_id')
                    ->where('result_create_exam.syear', $syear)
                    ->where('result_create_exam.sub_institute_id', $tenant);
            });
        }

        return $query;
    }

    /**
     * Active departments for this tenant — `status = 1` (or the enum true),
     * matching HRMS/departmentController::hierarchy() and indexManagement().
     *
     * Not year-scoped: `hrms_departments` has no `syear`, and the LMS's own
     * department screens list the institute's departments whole.
     */
    protected function lmsDepartments(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('hrms_departments')->where('hrms_departments.sub_institute_id', $this->tenantId);
        if (SchemaCache::hasColumn('hrms_departments', 'status')) {
            $query->where('hrms_departments.status', 1);
        }

        return $query;
    }

    /**
     * Active people for this tenant — INNER JOIN tbluserprofilemaster so users
     * with a dangling user_profile_id are excluded, matching
     * EmployeeDirectoryController::index().
     */
    protected function lmsPeople()
    {
        if (! SchemaCache::hasTable('tbluserprofilemaster')) {
            // Fallback: if the profile table genuinely does not exist, fall
            // back to a plain active-user count so the Brain still works.
            $query = DB::table('tbluser')->where('tbluser.sub_institute_id', $this->tenantId);
            if (SchemaCache::hasColumn('tbluser', 'status')) {
                $query->where('tbluser.status', 1);
            }

            return $query;
        }

        return DB::table('tbluser')
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->where('tbluser.sub_institute_id', $this->tenantId);
    }

    /**
     * Active students for this tenant — `status = 1`, matching
     * tblstudentController::index().
     *
     * THE ROLL IS NOT YEAR-SCOPED, AND THAT IS A DELIBERATE MATCH TO THE LMS.
     * It is tempting to scope it — `tblstudent_enrollment` has a `syear`, and
     * the operational screens (StudentSearchApiController, BulkStudentApiController,
     * the fees and hostel dashboards) do join it. But the LMS's own STUDENTS
     * screen does not: tblstudentController::index() lists
     * `sub_institute_id + status = 1` and nothing else, because the register is
     * master data the way departments and staff are. The Brain shows what that
     * screen shows.
     *
     * The evidence is also decisive in this database: 28 of 3,438 students have
     * an enrolment row for the current syear. Scoping the roll would report 28
     * students, drop every coverage ratio through its floor, and leave the Brain
     * disagreeing with the LMS by two orders of magnitude — a year filter that
     * is correct in principle and catastrophic in fact. If a year-scoped roll is
     * ever wanted, the LMS's Students screen has to move first; one source of
     * truth means the Brain follows it rather than pre-empting it.
     *
     * Year-scoped student ACTIVITY — attendance, homework, fees, marks — is
     * where a year actually lives, and those builders above do apply it.
     */
    protected function lmsStudents()
    {
        $query = DB::table('tblstudent')->where('tblstudent.sub_institute_id', $this->tenantId);
        if (SchemaCache::hasColumn('tblstudent', 'status')) {
            $query->where('tblstudent.status', 1);
        }

        return $query;
    }

    /**
     * Count the active population for a given LMS table, scoped by tenant.
     *
     * - hrms_departments: applies `status = 1` when the column exists
     * - tbluser: uses an INNER JOIN to tbluserprofilemaster (matching the LMS
     *   Employee Directory) — no status filter, because the LMS People view does
     *   not exclude inactive staff, only those with dangling profile ids.
     * - tblstudent: `status = 1`, and NOT year-scoped — see lmsStudents().
     * - anything else: `syear` is applied only when that table has the column.
     */
    protected function lmsCount(string $table): int
    {
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, 'sub_institute_id')) {
            return 0;
        }

        if ($table === 'tbluser' && SchemaCache::hasTable('tbluserprofilemaster')) {
            return (int) DB::table('tbluser')
                ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
                ->where('tbluser.sub_institute_id', $this->tenantId)
                ->count();
        }

        $query = DB::table($table)->where('sub_institute_id', $this->tenantId);
        if (SchemaCache::hasColumn($table, 'status')) {
            $query->where('status', 1);
        }

        return (int) $this->applyYear($query, $table)->count();
    }
}
