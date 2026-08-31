<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the corrections identified in the "Student access matrix" review
 * (see 2026_08_25_150000/150100/150200_*.php for the migrations this
 * builds on):
 *
 *   1. REVOKE — 8 menus the tenant-provisioning baseline
 *      (NewLMS_ApiController::INSERT_RIGHTS' $lmsstudent_rights, copy-pasted
 *      from the Teacher list) wrongly gave every Student full CRUD on:
 *      curriculum/content authoring and master-data screens. None of these
 *      are used by any student-facing flow already verified working.
 *   2. TIGHTEN — 6 menus that are correctly viewable (hub/browse/attend
 *      screens shared with staff) but still carry can_add = 1 from the same
 *      baseline. Viewing is untouched; only the write flag drops to 0.
 *   3. GRANT — 3 menus found missing during the review: the school
 *      calendar, the student's own leave-request page, and the newer
 *      /lms/dashboard duplicate of the already-granted LMS Dashboard.
 *
 * Menu ids are hardcoded rather than resolved by `link`: tblmenumaster is a
 * single global table shared by every tenant (sub_institute_id is a CSV
 * visibility column, not a per-tenant row), so a given menu's id is the same
 * everywhere — only tbluserprofilemaster/tblgroupwise_rights are per-tenant.
 * Idempotent; touches only the Student profile's own rights.
 */
return new class extends Migration
{
    private const PROFILE_NAME = 'Student';

    private const REVOKE_MENU_IDS = [
        231, // Chapter Master
        236, // Add Content
        275, // LMS Global Mapping
        327, // Curriculum Planning
        96,  // Lesson Planning
        153, // Book List
        154, // Syllabus
        311, // Leader Board Master
    ];

    private const TIGHTEN_MENU_IDS = [
        230, // LMS + PAL
        269, // Teach/Learn
        270, // All Courses
        242, // Exam
        464, // Virtual Classroom
        426, // PAL
    ];

    /** [menu_id, can_view, can_add, can_edit, can_delete] */
    private const GRANT_RIGHTS = [
        [20, 1, 0, 0, 0],  // Calendar
        [140, 1, 1, 0, 0], // Leave Application (student's own submission page)
        [518, 1, 0, 0, 0], // LMS Dashboard (newer /lms/dashboard duplicate)
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
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

        $profileIds = $profiles->pluck('id');

        // 1. Revoke — delete the row entirely, same as "never granted".
        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', self::REVOKE_MENU_IDS)
            ->whereIn('profile_id', $profileIds)
            ->delete();

        // 2. Tighten — keep can_view, drop can_add/can_edit/can_delete to 0.
        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', self::TIGHTEN_MENU_IDS)
            ->whereIn('profile_id', $profileIds)
            ->update(['can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);

        // 3. Grant — idempotent insert-if-missing, same pattern as prior migrations.
        foreach (self::GRANT_RIGHTS as [$menuId, $canView, $canAdd, $canEdit, $canDelete]) {
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
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
            return;
        }

        $profileIds = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('id');

        // Only the newly-granted rows are cleanly reversible — the revoked
        // and tightened rights were incorrect over-grants and are not
        // restored on rollback.
        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', array_column(self::GRANT_RIGHTS, 0))
            ->whereIn('profile_id', $profileIds)
            ->delete();
    }
};
