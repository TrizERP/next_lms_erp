<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrects an omission in 2026_08_25_150000_grant_student_role_full_workflow_rights.php:
 * `lmsdashboard.index` ("LMS Dashboard", the app/lms/dashboard page every
 * student lands on) was left out of that migration's link list, so
 * checkPermission::handle finds no tblgroupwise_rights row for the Student
 * profile on that menu and throws AuthorizationException (HTTP 403) for
 * every student, on every tenant. `lmsdashboard_teacher` (a separate route)
 * correctly stays staff-only and is not touched here.
 *
 * Same idempotent find-or-insert convention as the migration it corrects.
 */
return new class extends Migration
{
    private const PROFILE_NAME = 'Student';
    private const LINK = 'lmsdashboard.index';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', self::LINK)->value('id');
        if ($menuId === null) {
            return;
        }

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'sub_institute_id')
            ->where('name', self::PROFILE_NAME)
            ->where('status', 1)
            ->get();

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

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', self::LINK)->value('id');
        if ($menuId === null) {
            return;
        }

        $studentProfileIds = DB::table('tbluserprofilemaster')
            ->where('name', self::PROFILE_NAME)
            ->pluck('id');

        DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)
            ->whereIn('profile_id', $studentProfileIds)
            ->delete();
    }
};
