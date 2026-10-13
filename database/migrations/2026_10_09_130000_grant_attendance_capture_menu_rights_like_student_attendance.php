<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the "Capture Attendance" (501) and "Capture Student Photo" (500) tabs on
 * Attendance -> Operations visible to the people who already work in that section.
 *
 * 2026_10_09_120000 placed both menus in the Operations category, but a category tab is
 * only shown to a user holding a rights row for its menu, and these two menus had rights
 * for just two profiles (school 342 Admin, school 61 Teacher). Every other school saw
 * only "Student Attendance".
 *
 * This gives each staff profile that already holds Student Attendance (menu 95) the same
 * rights (same can_view/add/edit/delete) on 500 and 501. Student and Parent profiles are
 * left out - neither screen is theirs to use here. Existing rows are never changed: a
 * (menu, profile, school) that already has a row is skipped, so the two pre-existing
 * grants and any hand-tuned rights survive. Whether a user may actually upload or delete
 * photos is still decided by the Capture Photo API, not by this row.
 *
 * Run on its own - never a bare `php artisan migrate` on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_10_09_130000_grant_attendance_capture_menu_rights_like_student_attendance.php
 */
return new class extends Migration
{
    private const SOURCE_MENU = 95;               // Student Attendance
    private const TARGET_MENUS = [501, 500];       // Capture Attendance, Capture Student Photo
    /** Rows that existed before this migration and must survive down(): [menu => [[school, profile]]]. */
    private const PRE_EXISTING = [[342, 3686], [61, 183]];

    public function up(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $sources = DB::table('tblgroupwise_rights as g')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'g.profile_id')
            ->where('g.menu_id', self::SOURCE_MENU)
            ->whereNotIn(DB::raw('LOWER(TRIM(p.name))'), ['student', 'parent'])
            ->get(['g.profile_id', 'g.sub_institute_id', 'g.can_view', 'g.can_add', 'g.can_edit', 'g.can_delete']);

        foreach (self::TARGET_MENUS as $menuId) {
            $have = DB::table('tblgroupwise_rights')->where('menu_id', $menuId)
                ->get(['profile_id', 'sub_institute_id'])
                ->mapWithKeys(fn ($r) => [$r->sub_institute_id.'|'.$r->profile_id => true])->all();

            $rows = [];
            foreach ($sources as $source) {
                if (isset($have[$source->sub_institute_id.'|'.$source->profile_id])) {
                    continue;
                }
                $rows[] = [
                    'menu_id' => $menuId,
                    'profile_id' => $source->profile_id,
                    'sub_institute_id' => $source->sub_institute_id,
                    'can_view' => $source->can_view,
                    'can_add' => $source->can_add,
                    'can_edit' => $source->can_edit,
                    'can_delete' => $source->can_delete,
                    'is_mobile' => 0,
                    'created_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('tblgroupwise_rights')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        DB::table('tblgroupwise_rights')->whereIn('menu_id', self::TARGET_MENUS)
            ->where(function ($q) {
                foreach (self::PRE_EXISTING as [$school, $profile]) {
                    $q->where(fn ($w) => $w->where('sub_institute_id', '!=', $school)->orWhere('profile_id', '!=', $profile));
                }
            })->delete();
    }
};
