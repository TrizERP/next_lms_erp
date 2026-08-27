<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every tenant's "Student" profile (tbluserprofilemaster) full,
 * self-service access to the student workflow: own profile/attendance/fees
 * view, documents, health, ID card, certificates, homework (view + submit),
 * requests (view + submit), exam schedule, online exam attempt, progress
 * report, results/report card, assignments (view + submit), doubts/Q&A,
 * leaderboard and content-library consumption.
 *
 * Deliberately excludes anything that manages other students or the
 * institute (search/bulk-update/transfer student, marks entry, exam/question
 * authoring, fee collection, result publishing, reports that aggregate
 * across students, settings, HR/organization management, etc.) — those
 * links are simply never added here, and absence of a tblgroupwise_rights
 * row is "no access" (see checkPermission::handle, MenuMiddleware). This
 * migration never touches any other profile's rights.
 *
 * `link` values are route names (see checkPermission.php:40 — menu_id is
 * resolved by exact `tblmenumaster.link` == current route name, no
 * per-tenant filtering), so the same link is looked up once and applied to
 * every tenant's Student profile. Same idempotent find-or-insert convention
 * as 2026_08_20_100700_grant_task_management_menu_rights.php and siblings.
 *
 * Also backfills a missing "Student" tbluserprofilemaster row for any
 * tenant that has tblstudent records but, for whatever historical reason,
 * never got one — mirrors the row NewLMS_ApiController::INSERT_USERPROFILEMASTER
 * creates for every newly provisioned institute.
 */
return new class extends Migration
{
    private const PROFILE_NAME = 'Student';

    /**
     * [link, can_view, can_add, can_edit, can_delete]. can_edit/can_delete
     * are never granted here — nothing in this workflow lets a student
     * modify or remove an existing record, only view or submit new ones.
     */
    private const RIGHTS = [
        // Own profile / attendance / fees / documents (view only)
        ['student_attendance.index', 1, 0, 0, 0],
        ['student_attendance.show', 1, 0, 0, 0],
        ['student_graph_attendance.index', 1, 0, 0, 0],
        ['student_graph_attendance.show', 1, 0, 0, 0],
        ['student_fees_detail.index', 1, 0, 0, 0],
        ['student_fees_detail.show', 1, 0, 0, 0],
        ['student_fees_graph.index', 1, 0, 0, 0],
        ['student_fees_graph.show', 1, 0, 0, 0],
        ['student_document.index', 1, 0, 0, 0],
        ['student_document.show', 1, 0, 0, 0],

        // Health / ID card / certificates (view only)
        ['student_infirmary.index', 1, 0, 0, 0],
        ['student_infirmary.show', 1, 0, 0, 0],
        ['student_vaccination.index', 1, 0, 0, 0],
        ['student_vaccination.show', 1, 0, 0, 0],
        ['student_health.index', 1, 0, 0, 0],
        ['student_health.show', 1, 0, 0, 0],
        ['student_health_report', 1, 0, 0, 0],
        ['show_student_health_report', 1, 0, 0, 0],
        ['student_icard.index', 1, 0, 0, 0],
        ['student_icard.show', 1, 0, 0, 0],
        ['show_student_icard', 1, 0, 0, 0],
        ['view_samples', 1, 0, 0, 0],
        ['student_certificate.index', 1, 0, 0, 0],
        ['student_certificate.show', 1, 0, 0, 0],
        ['show_student_certificate', 1, 0, 0, 0],

        // Homework (view) + submission (view + add)
        ['student_homework.index', 1, 0, 0, 0],
        ['student_homework.show', 1, 0, 0, 0],
        ['ajax_getHomeworkSubjects', 1, 0, 0, 0],
        ['student_homework_submission.index', 1, 1, 0, 0],
        ['student_homework_submission.show', 1, 1, 0, 0],
        ['student_homework_submission.store', 1, 1, 0, 0],

        // Requests (view + submit; staff-only "status" approve/reject is excluded)
        ['student_request.index', 1, 0, 0, 0],
        ['student_request.show', 1, 0, 0, 0],
        ['student_request.store', 1, 1, 0, 0],

        // Front-desk: exam schedule (view), circulars/notices, gallery (view)
        ['exam_schedule.index', 1, 0, 0, 0],
        ['exam_schedule.show', 1, 0, 0, 0],
        ['circular.index', 1, 0, 0, 0],
        ['photo_video_gallary.index', 1, 0, 0, 0],
        ['photo_video_gallary.show', 1, 0, 0, 0],

        // Online exam: attempt only (exam authoring stays admin/teacher-only)
        ['online_exam_attempt', 1, 0, 0, 0],
        ['ajax_getQuestionList', 1, 0, 0, 0],

        // LMS: progress/report, portfolio, activity, communication (view)
        ['lmsExamwise_progress_report.index', 1, 0, 0, 0],
        ['lmsExamwise_progress_report.show', 1, 0, 0, 0],
        ['lmsStudent_report.index', 1, 0, 0, 0],
        ['lmsStudent_report.show', 1, 0, 0, 0],
        ['lmsPortfolio.index', 1, 0, 0, 0],
        ['lmsPortfolio.show', 1, 0, 0, 0],
        ['lmsActivityStream.index', 1, 0, 0, 0],
        ['lmsCommunication.index', 1, 0, 0, 0],
        ['lmsCommunication.show', 1, 0, 0, 0],
        ['lmsLeaderboard.index', 1, 0, 0, 0],
        ['lmsLeaderboard.show', 1, 0, 0, 0],

        // LMS: doubts / Q&A (view + post)
        ['lmsDoubt.index', 1, 1, 0, 0],
        ['lmsDoubt.show', 1, 1, 0, 0],
        ['lmsDoubt.store', 1, 1, 0, 0],
        ['lmsDoubtConversation.index', 1, 1, 0, 0],
        ['lmsDoubtConversation.show', 1, 1, 0, 0],
        ['lmsDoubtConversation.store', 1, 1, 0, 0],

        // LMS: assignments (view + submit)
        ['lmsAssignment.index', 1, 0, 0, 0],
        ['lmsAssignment.show', 1, 0, 0, 0],
        ['lmsAssignment_submission.index', 1, 1, 0, 0],
        ['lmsAssignment_submission.show', 1, 1, 0, 0],
        ['lmsAssignment_submission.store', 1, 1, 0, 0],

        // LMS: content library / interactive content consumption (view only)
        ['lms_flashcard.index', 1, 0, 0, 0],
        ['lms_flashcard.show', 1, 0, 0, 0],
        ['lms_gamma_ppt.index', 1, 0, 0, 0],
        ['lms_gamma_ppt.show', 1, 0, 0, 0],
        ['content_library.index', 1, 0, 0, 0],
        ['content_library.show', 1, 0, 0, 0],
        ['searchContent', 1, 0, 0, 0],
        ['downloadFile', 1, 0, 0, 0],
        ['getMapVals', 1, 0, 0, 0],
        ['html_contents.index', 1, 0, 0, 0],
        ['html_contents.show', 1, 0, 0, 0],
        ['scenario_based.index', 1, 0, 0, 0],
        ['scenario_based.show', 1, 0, 0, 0],
        ['h5p.index', 1, 0, 0, 0],
        ['h5p.show', 1, 0, 0, 0],
        ['h5p_mcq.index', 1, 0, 0, 0],
        ['h5p_mcq.show', 1, 0, 0, 0],
        ['h5p_interactive_video.index', 1, 0, 0, 0],
        ['h5p_interactive_video.show', 1, 0, 0, 0],
        ['h5p_flashacard.index', 1, 0, 0, 0],
        ['h5p_flashacard.show', 1, 0, 0, 0],

        // Results / report card (view only)
        ['all_results.index', 1, 0, 0, 0],
        ['all_results.show', 1, 0, 0, 0],
        ['student-result.index', 1, 0, 0, 0],
        ['student-result.show', 1, 0, 0, 0],
        ['current_result', 1, 0, 0, 0],
        ['student-result-remarks.index', 1, 0, 0, 0],
        ['student-result-remarks.show', 1, 0, 0, 0],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $this->backfillMissingStudentProfiles();

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'sub_institute_id')
            ->where('name', self::PROFILE_NAME)
            ->where('status', 1)
            ->get();

        if ($profiles->isEmpty()) {
            return;
        }

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

        if ($menuIds->isEmpty()) {
            return;
        }

        $studentProfileIds = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('id');

        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', $menuIds)
            ->whereIn('profile_id', $studentProfileIds)
            ->delete();
    }

    /**
     * Mirrors NewLMS_ApiController::INSERT_USERPROFILEMASTER's "Student"
     * entry for any tenant that already has student records but is missing
     * the profile row (older tenants provisioned before that flow existed).
     * Never removed in down() — other data may already reference it.
     */
    private function backfillMissingStudentProfiles(): void
    {
        if (! Schema::hasTable('tblstudent')) {
            return;
        }

        $subInstitutesWithStudents = DB::table('tblstudent')
            ->whereNotNull('sub_institute_id')
            ->distinct()
            ->pluck('sub_institute_id');

        $subInstitutesWithStudentProfile = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('sub_institute_id');

        $missing = $subInstitutesWithStudents->diff($subInstitutesWithStudentProfile);

        foreach ($missing as $subInstituteId) {
            $nextSortOrder = (int) DB::table('tbluserprofilemaster')
                ->where('sub_institute_id', $subInstituteId)
                ->max('sort_order') + 1;

            DB::table('tbluserprofilemaster')->insert([
                'parent_id' => 0,
                'name' => self::PROFILE_NAME,
                'description' => self::PROFILE_NAME,
                'sort_order' => $nextSortOrder,
                'status' => 1,
                'sub_institute_id' => $subInstituteId,
                'client_id' => $subInstituteId,
            ]);
        }
    }
};
