<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Task Management" as a new top-level (level 1) sidebar module,
 * for the as-is migration of hp_erp's Task Management module.
 *
 * Modelled exactly on `2026_08_18_120000_add_talent_management_menu.php`,
 * extended to 3 menu levels instead of 2: level 1 is the module row, level 2
 * has 6 direct items plus one "Administration" row, and level 3 holds 4 rows
 * parented under that Administration row's freshly-inserted id.
 *
 * sub_institute_id: same reasoning as the Talent Management precedent — this
 * is a brand-new, parent-less top-level module, so every currently-
 * provisioned institute is enumerated from `school_detail` at migration time
 * rather than copied from a parent row.
 *
 * Idempotent: safe to re-run — existing rows (matched by `link`) are left
 * untouched rather than duplicated.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'task_management.index';

    private const ADMINISTRATION_LINK = 'task_management.administration';

    /** Level-2 rows, in display order. Administration is last, like Talent Management. */
    private const LEVEL2_MENUS = [
        ['name' => 'Task Management Dashboard', 'link' => 'task_management.dashboard', 'description' => 'Task Management: workload, delivery and delay overview'],
        ['name' => 'My Tasks', 'link' => 'task_management.my_tasks', 'description' => 'Tasks assigned to or created by the current user'],
        ['name' => 'Projects & Workstreams', 'link' => 'task_management.projects_workstreams', 'description' => 'Projects, workstreams and project tasks'],
        ['name' => 'Dependencies & Workstreams', 'link' => 'task_management.dependencies_workstreams', 'description' => 'Task dependencies, predecessors and successors'],
        ['name' => 'Task Calendar', 'link' => 'task_management.calendar', 'description' => 'Calendar view of tasks, milestones and deadlines'],
        ['name' => 'Reports & Analysis', 'link' => 'task_management.reports_analysis', 'description' => 'Task delivery, capacity and delay reports'],
        ['name' => 'Administration', 'link' => self::ADMINISTRATION_LINK, 'description' => 'Task Management workflow configuration'],
    ];

    /** Level-3 rows, parented under the Administration row. */
    private const LEVEL3_MENUS = [
        ['name' => 'Status Management', 'link' => 'task_management.admin.status_management', 'description' => 'Custom task statuses and priorities'],
        ['name' => 'Permissions Matrix', 'link' => 'task_management.admin.permissions_matrix', 'description' => 'Role-based access to Task Management features'],
        ['name' => 'Integration', 'link' => 'task_management.admin.integration', 'description' => 'External integrations for Task Management'],
        ['name' => 'Audit Logs', 'link' => 'task_management.admin.audit_logs', 'description' => 'Task Management audit trail'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $subInstituteId = $this->resolveSubInstituteIdCsv();
        $clientId = $this->resolveClientId();

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            $moduleSort = (int) DB::table('tblmenumaster')->where('parent_menu_id', 0)->max('sort_order');

            $moduleId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Task Management',
                'menu_title' => 'Task Management',
                'description' => 'Project, task, dependency and delivery management',
                'parent_menu_id' => 0,
                'level' => 1,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::MODULE_LINK,
                'icon' => 'mdi mdi-clipboard-check-outline',
                'sub_institute_id' => $subInstituteId,
                'client_id' => $clientId,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'Task Management',
                'menu_path' => 'Task Management',
                'created_at' => now(),
            ]);
            $module = DB::table('tblmenumaster')->where('id', $moduleId)->first();
        }

        $menuSort = 0;
        foreach (self::LEVEL2_MENUS as $menu) {
            $menuSort++;

            if (DB::table('tblmenumaster')->where('link', $menu['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $menu['name'],
                'menu_title' => 'Task Management',
                'description' => $menu['description'],
                'parent_menu_id' => $module->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => $menuSort,
                'link' => $menu['link'],
                'icon' => 'mdi mdi-clipboard-check-outline',
                'sub_institute_id' => $module->sub_institute_id,
                'client_id' => $module->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $menu['name'],
                'menu_path' => $menu['name'],
                'created_at' => now(),
            ]);
        }

        $administration = DB::table('tblmenumaster')->where('link', self::ADMINISTRATION_LINK)->first();
        if ($administration === null) {
            // Administration row failed to insert above for some reason; nothing to parent level 3 under.
            return;
        }

        $level3Sort = 0;
        foreach (self::LEVEL3_MENUS as $menu) {
            $level3Sort++;

            if (DB::table('tblmenumaster')->where('link', $menu['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $menu['name'],
                'menu_title' => 'Task Management',
                'description' => $menu['description'],
                'parent_menu_id' => $administration->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $level3Sort,
                'link' => $menu['link'],
                'icon' => 'mdi mdi-clipboard-check-outline',
                'sub_institute_id' => $administration->sub_institute_id,
                'client_id' => $administration->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $menu['name'],
                'menu_path' => $menu['name'],
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = [self::MODULE_LINK];
        foreach (self::LEVEL2_MENUS as $menu) {
            $links[] = $menu['link'];
        }
        foreach (self::LEVEL3_MENUS as $menu) {
            $links[] = $menu['link'];
        }

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
        if ($menuIds !== [] && Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('link', $links)->delete();
    }

    /**
     * Every currently-provisioned institute, as a CSV — see the class
     * doc-block for why this (rather than a parent copy or a wildcard) is
     * used for this specific, parent-less top-level module.
     */
    private function resolveSubInstituteIdCsv(): string
    {
        if (! Schema::hasTable('school_detail')) {
            return '1';
        }

        $ids = DB::table('school_detail')->pluck('id')->all();
        if ($ids === []) {
            return '1';
        }

        return implode(',', $ids);
    }

    /**
     * client_id is shared/global across a tenant's schools, not per-institute
     * — reuse whatever an existing top-level module already has rather than
     * inventing a value.
     */
    private function resolveClientId(): ?string
    {
        return DB::table('tblmenumaster')
            ->where('level', 1)
            ->whereNotNull('client_id')
            ->where('client_id', '!=', '')
            ->value('client_id');
    }
};
