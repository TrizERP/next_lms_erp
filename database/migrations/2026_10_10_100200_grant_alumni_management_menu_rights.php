<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the Alumni Management menu tree (module + its menus) so it renders.
 * Admin-equivalent profiles plus an 'Alumni Coordinator' profile where a tenant has created one.
 * Re-runnable for tenants provisioned later (existing grants are skipped).
 */
return new class extends Migration
{
    private const MODULE_LINK = 'alumni_management.index';

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin', 'Alumni Coordinator'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuIds = $this->alumniMenuIds();
        if ($menuIds === []) {
            return;
        }

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'sub_institute_id')
            ->whereIn('name', self::ADMIN_PROFILE_NAMES)
            ->where('status', 1)
            ->get();

        foreach ($menuIds as $menuId) {
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
                    'can_add' => 1,
                    'can_edit' => 1,
                    'can_delete' => 1,
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

        $menuIds = $this->alumniMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    private function alumniMenuIds(): array
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return [];
        }

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            return [];
        }

        $menuIds = [$module->id];

        $menus = DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->pluck('id');
        foreach ($menus as $menuId) {
            $menuIds[] = $menuId;
        }

        return $menuIds;
    }
};
