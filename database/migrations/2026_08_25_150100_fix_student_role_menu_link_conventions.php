<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects 2026_08_25_150000_grant_student_role_full_workflow_rights.php.
 *
 * That migration assumed tblmenumaster.link always stores a Laravel route
 * name (the convention checkPermission.php matches against). Auditing the
 * live table showed most current student-facing screens instead store a
 * frontend URL path (e.g. `/student/student_attendance`) — only a handful
 * of older entries (exam_schedule.index, circular.index,
 * student_homework_submission.index, lmsExamwise_progress_report.index,
 * lmsPortfolio.index, lmsActivityStream.index, lmsCommunication.index,
 * lmsLeaderboard.index, lmsAssignment.index, lmsAssignment_submission.index,
 * all_results.index, student-result.index) use the route-name convention.
 * As a result the prior migration silently skipped ~75 of its 91 intended
 * links (no matching tblmenumaster row), leaving attendance, health,
 * infirmary, vaccination, ID card, certificate, documents, homework and
 * online-exam menus ungranted for Student despite Admin/Teacher/School
 * Admin already holding them.
 *
 * This migration:
 *   1. Grants the correct path-based links, verified against the live
 *      tblmenumaster table for every tenant.
 *   2. Reverts `lmsStudent_report.index` ("Student Analysis Report"),
 *      wrongly granted by the prior migration — its naming matches the
 *      other confirmed cross-student aggregate reports (Student Discipline
 *      Report, Student Request Report, Student Certificate Report), not a
 *      personal-progress view, so it should not have been treated as
 *      student-safe without controller-level verification.
 *
 * Same idempotent per-tenant find-or-insert convention as the migration it
 * corrects.
 */
return new class extends Migration
{
    private const PROFILE_NAME = 'Student';

    /** Wrongly granted by the prior migration; revert for Student only. */
    private const REVOKE_LINKS = [
        'lmsStudent_report.index',
        'lmsStudent_report.show',
    ];

    /** [link, can_view, can_add, can_edit, can_delete] */
    private const RIGHTS = [
        ['/student/student_attendance', 1, 0, 0, 0],
        ['/student/student_infirmary', 1, 0, 0, 0],
        ['/student/student_vaccination', 1, 0, 0, 0],
        ['/student/student_hw', 1, 0, 0, 0],
        ['/student/student_health', 1, 0, 0, 0],
        ['/student/report/student_health_report', 1, 0, 0, 0],
        ['/student/student_icard', 1, 0, 0, 0],
        ['/student/student_certificate', 1, 0, 0, 0],
        ['students/student_documents/', 1, 0, 0, 0],
        ['/student_homework/', 1, 0, 0, 0],
        ['/lms/homework', 1, 0, 0, 0],
        ['/exam/online', 1, 0, 0, 0],
        ['/exam/progress-report', 1, 0, 0, 0],
        ['/result/student_result_remarks', 1, 0, 0, 0],
        ['/students/requests/', 1, 1, 0, 0],
        ['/lms/homework/submission', 1, 1, 0, 0],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'sub_institute_id')
            ->where('name', self::PROFILE_NAME)
            ->where('status', 1)
            ->get();

        if ($profiles->isEmpty()) {
            return;
        }

        $this->revokeMisgrantedRights($profiles);

        foreach (self::RIGHTS as [$link, $canView, $canAdd, $canEdit, $canDelete]) {
            $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', $link)->value('id');
            if ($menuId === null) {
                continue;
            }

            foreach ($profiles as $profile) {
                $exists = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $profile->id)
                    ->where('sub_institute_id', $profile->sub_institute_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $menuId,
                    'profile_id' => $profile->id,
                    'sub_institute_id' => $profile->sub_institute_id,
                    'can_view' => $canView,
                    'can_add' => $canAdd,
                    'can_edit' => $canEdit,
                    'can_delete' => $canDelete,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereIn('link', array_column(self::RIGHTS, 0))
            ->pluck('id');

        $studentProfileIds = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('id');

        if ($menuIds->isNotEmpty()) {
            DB::table('tblgroupwise_rights')
                ->whereIn('menu_id', $menuIds)
                ->whereIn('profile_id', $studentProfileIds)
                ->delete();
        }
    }

    private function revokeMisgrantedRights($profiles): void
    {
        $menuIds = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereIn('link', self::REVOKE_LINKS)
            ->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', $menuIds)
            ->whereIn('profile_id', $profiles->pluck('id'))
            ->delete();
    }
};
