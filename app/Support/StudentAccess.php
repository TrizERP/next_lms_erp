<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether the signed-in caller may read one student's records.
 *
 * Result, assessment, fee and similar endpoints took a student id from the URL or the
 * request and trusted it, so any logged-in user could read any student in any school.
 * A student or parent may see only their own record; staff only students of their school.
 * Reads the verified session that the `session` / `api.session` middleware filled in.
 */
class StudentAccess
{
    public static function allows($studentId): bool
    {
        return self::tenantFor($studentId) !== null;
    }

    /** The caller's school when access is allowed, or null. */
    public static function tenantFor($studentId): ?int
    {
        $tenant = (int) session()->get('sub_institute_id');
        $callerId = (int) session()->get('user_id');
        if ($tenant <= 0 || $callerId <= 0 || (int) $studentId <= 0) {
            return null;
        }

        $profile = strtolower((string) session()->get('user_profile_name'));
        if (session()->get('is_student') || in_array($profile, ['student', 'parent'], true)) {
            return (int) $studentId === $callerId ? $tenant : null;
        }

        $inSchool = DB::table('tblstudent')
            ->where('id', (int) $studentId)
            ->where('sub_institute_id', $tenant)
            ->exists();

        return $inSchool ? $tenant : null;
    }
}
