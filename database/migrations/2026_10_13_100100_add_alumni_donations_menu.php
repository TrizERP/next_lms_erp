<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu entry for the Donations screen, under Alumni Management (inside Institute ERP).
 *
 * SECURITY: unlike the other alumni screens this is NOT copied from the "Alumni List" rights. Donations sit behind the
 * `finance` gate in the controller (Admin-type profiles and Accountant), so only profiles with those names are granted
 * the menu - an Alumni Coordinator manages alumni but not money and would only see a screen that answers 403. The
 * Accountant also gets view of the "Alumni Management" module row, because the sidebar hides a child whose parent the
 * profile cannot see; that shows them the module with the Donations entry only.
 *
 * Granting menu access to Accountant is a PRODUCT/SECURITY DECISION for the owner to confirm before this is applied
 * (see the plan, decision 7). Idempotent; forward-only down().
 */
return new class extends Migration
{
    private const FINANCE_PROFILES = ['ADMIN', 'SUPER ADMIN', 'SCHOOL ADMIN', 'ASSISTANT ADMIN', 'ACCOUNTANT'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
            return;
        }
        $module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->first();
        $reference = DB::table('tblmenumaster')->where('link', 'alumni/list')->first();
        if ($module === null || $reference === null) {
            return;
        }
        $id = DB::table('tblmenumaster')->where('link', 'alumni/donations')->value('id');
        if ($id === null) {
            $id = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Donations', 'menu_title' => 'Alumni Management', 'description' => 'Record, verify and receipt alumni donations; campaigns and refunds',
                'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1, 'sort_order' => (int) DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->max('sort_order') + 1,
                'link' => 'alumni/donations', 'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id, 'client_id' => $module->client_id,
                'menu_type' => 'ENTRY', 'site_map_name' => 'Donations', 'menu_path' => 'Donations', 'created_at' => now(),
            ]);
        }
        $tenants = array_filter(array_map('trim', explode(',', (string) $module->sub_institute_id)), 'strlen');
        $grant = function (int $menuId, int $profileId, int $tenant, int $add, int $edit): void {
            if (! DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->where('profile_id', $profileId)->where('sub_institute_id', $tenant)->exists()) {
                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $menuId, 'profile_id' => $profileId, 'sub_institute_id' => $tenant, 'can_view' => 1, 'can_add' => $add, 'can_edit' => $edit, 'can_delete' => 0, 'created_at' => now(),
                ]);
            }
        };
        $profiles = DB::table('tbluserprofilemaster')->where('status', 1)->whereIn('sub_institute_id', $tenants)->get(['id', 'name', 'sub_institute_id'])
            ->filter(fn ($p) => in_array(strtoupper(trim((string) $p->name)), self::FINANCE_PROFILES, true));
        foreach ($profiles as $p) {
            $grant((int) $id, (int) $p->id, (int) $p->sub_institute_id, 1, 1);          // no delete: nothing about a donation is ever deleted
            $grant((int) $module->id, (int) $p->id, (int) $p->sub_institute_id, 0, 0);   // so the sidebar shows the parent (no-op where already granted)
        }
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
