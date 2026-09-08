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
 */
trait LmsQueryScope
{
    /**
     * Active departments for this tenant — `status = 1` (or the enum true),
     * matching HRMS/departmentController::hierarchy() and indexManagement().
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
     * - hrms_departments / tblstudent: applies `status = 1` when the column exists
     * - tbluser: uses an INNER JOIN to tbluserprofilemaster (matching the LMS
     *   Employee Directory) — no status filter, because the LMS People view does
     *   not exclude inactive staff, only those with dangling profile ids.
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

        return (int) $query->count();
    }
}
