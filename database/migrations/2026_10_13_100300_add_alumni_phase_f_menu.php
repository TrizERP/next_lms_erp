<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu entries for the Phase F screens, under Alumni Management (inside Institute ERP):
 *   Reports, Import   - copy the rights of the existing "Alumni List" menu: everyone who can manage alumni sees them.
 *   Audit Log         - NOT copied. The log shows who did what, including donation amounts, so only Admin-type profiles (the
 *                       same names the controller's `audit` gate accepts) are granted it, view only.
 *
 * Idempotent: rows are matched by link, a rights row is only added when missing. Forward-only down().
 */
return new class extends Migration
{
    private const MANAGER_MENUS = [
        ['name' => 'Alumni Reports', 'link' => 'alumni/reports', 'description' => 'Reports and exports: master, batch-wise, career, education, location, events, engagement, donations'],
        ['name' => 'Import Alumni', 'link' => 'alumni/import', 'description' => 'Bulk import from Excel or CSV with column mapping, preview and duplicate detection'],
    ];

    private const AUDIT_MENU = ['name' => 'Alumni Audit Log', 'link' => 'alumni/audit', 'description' => 'Who did what in the alumni module'];

    private const AUDIT_PROFILES = ['ADMIN', 'SUPER ADMIN', 'SCHOOL ADMIN', 'ASSISTANT ADMIN'];

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
        $sort = (int) DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->max('sort_order');
        $ensure = function (array $menu) use ($module, $reference, &$sort): int {
            $id = DB::table('tblmenumaster')->where('link', $menu['link'])->value('id');
            if ($id === null) {
                $sort++;
                $id = DB::table('tblmenumaster')->insertGetId([
                    'name' => $menu['name'], 'menu_title' => 'Alumni Management', 'description' => $menu['description'], 'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1,
                    'sort_order' => $sort, 'link' => $menu['link'], 'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id, 'client_id' => $module->client_id,
                    'menu_type' => 'ENTRY', 'site_map_name' => $menu['name'], 'menu_path' => $menu['name'], 'created_at' => now(),
                ]);
            }

            return (int) $id;
        };
        $grant = function (int $menuId, int $profileId, int $tenant, int $view, int $add, int $edit, int $delete): void {
            if (! DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->where('profile_id', $profileId)->where('sub_institute_id', $tenant)->exists()) {
                DB::table('tblgroupwise_rights')->insert(['menu_id' => $menuId, 'profile_id' => $profileId, 'sub_institute_id' => $tenant,
                    'can_view' => $view, 'can_add' => $add, 'can_edit' => $edit, 'can_delete' => $delete, 'created_at' => now()]);
            }
        };

        $referenceRights = DB::table('tblgroupwise_rights')->where('menu_id', $reference->id)->get();
        foreach (self::MANAGER_MENUS as $menu) {
            $id = $ensure($menu);
            foreach ($referenceRights as $r) {
                $grant($id, (int) $r->profile_id, (int) $r->sub_institute_id, (int) $r->can_view, (int) $r->can_add, (int) $r->can_edit, (int) $r->can_delete);
            }
        }

        $audit = $ensure(self::AUDIT_MENU);
        $tenants = array_filter(array_map('trim', explode(',', (string) $module->sub_institute_id)), 'strlen');
        $admins = DB::table('tbluserprofilemaster')->where('status', 1)->whereIn('sub_institute_id', $tenants)->get(['id', 'name', 'sub_institute_id'])
            ->filter(fn ($p) => in_array(strtoupper(trim((string) $p->name)), self::AUDIT_PROFILES, true));
        foreach ($admins as $p) {
            $grant($audit, (int) $p->id, (int) $p->sub_institute_id, 1, 0, 0, 0);   // view only
            $grant((int) $module->id, (int) $p->id, (int) $p->sub_institute_id, 1, 0, 0, 0);
        }
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
