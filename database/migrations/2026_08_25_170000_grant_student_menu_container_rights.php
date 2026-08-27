<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants Student a bare can_view on the container/section nodes that sit
 * above menus already granted by 2026_08_25_150000-160000_*.php.
 *
 * MenuRightsController::getMenuRightsLevelWise (the /api/menu-rights
 * endpoint the Next.js sidebar renders from — MenuMiddleware is its
 * Blade-era twin, skipped entirely for type=API requests) builds the tree
 * top-down: a level-1 root only renders if its own id carries rights, and a
 * level-2/3 item is grouped under its parent_menu_id only once that parent
 * is already in the rendered set. A leaf with its own rights row but an
 * ungranted ancestor is therefore invisible in the sidebar even though its
 * page and backend route are fully accessible by direct link — exactly what
 * surfaced this session (Attendance, Certificate, Student Medical, Student
 * Request, Mobile Apps, Reports all missing despite their leaves being
 * granted).
 *
 * Every id below is a pure container (link is `javascript:void(0)` or a
 * grouping page) sitting directly above an already-granted leaf — granting
 * view-only here changes nothing about what data or actions a student can
 * reach, only whether the branch is visible at all.
 */
return new class extends Migration
{
    private const PROFILE_NAME = 'Student';

    private const CONTAINER_MENU_IDS = [
        1,   // Institute ERP (root) — parent of Attendance/I-card/Certificate/Medical/Request/Mobile Apps/Circular/Student
        4,   // Reports (root) — parent of Student Report/Exam Report/LMS Report
        54,  // Exam (Master) (root) — parent of Student Result Remark
        18,  // Mobile Apps — parent of Calendar/Gallery/Leave Application/Exam Schedule
        259, // Student — parent of Student Documents
        265, // Attendance — parent of Student Attendance
        261, // Student I-card
        262, // Certificate
        263, // Student Medical — parent of Infirmary/Vaccination/Height-Weight/Health
        264, // Student Request
        72,  // Exam Report — parent of All/New Report Card
        91,  // Student Report — parent of Student Health Report
        309, // LMS Report — parent of LMS Dashboard/Examwise Progress Report
        505, // Homework — parent of the newer /lms/homework paths
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

        foreach (self::CONTAINER_MENU_IDS as $menuId) {
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
                    'can_view' => 1,
                    'can_add' => 0,
                    'can_edit' => 0,
                    'can_delete' => 0,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $studentProfileIds = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('id');

        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', self::CONTAINER_MENU_IDS)
            ->whereIn('profile_id', $studentProfileIds)
            ->delete();
    }
};
