<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Alumni Management" as a top-level (level 1) sidebar module with its level-2 menus (only screens that exist in the frontend; later phases add theirs in new migrations).
 * Each link is the Next.js route (app/alumni/<screen>) - see the lms_k12 repo.
 *
 * Same shape as 2026_08_18_120000_add_talent_management_menu.php: parent-less module, so
 * every provisioned institute is enumerated from school_detail; idempotent (matched by link).
 * Pair with 2026_10_10_100200_grant_alumni_management_menu_rights.php - a menu with no rights
 * row is invisible to everyone.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'alumni_management.index';

    private const MENUS = [
        ['name' => 'Alumni Dashboard', 'link' => 'alumni/dashboard', 'description' => 'Alumni KPIs, batches, careers and recent registrations'],
        ['name' => 'Alumni List', 'link' => 'alumni/list', 'description' => 'Search, filter and manage alumni records'],
        ['name' => 'Add Alumni', 'link' => 'alumni/add', 'description' => 'Register an alumnus manually'],
        ['name' => 'Pending Verification', 'link' => 'alumni/pending_verification', 'description' => 'Review self-registered alumni'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $subInstituteId = $this->resolveSubInstituteIdCsv();
        $clientId = $this->resolveClientId();

        // by name too: 2026_10_10_100400 later moves the module under Institute ERP and changes its link
        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first()
            ?? DB::table('tblmenumaster')->where('name', 'Alumni Management')->first();
        if ($module === null) {
            $moduleSort = (int) DB::table('tblmenumaster')->where('parent_menu_id', 0)->max('sort_order');

            $moduleId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Alumni Management',
                'menu_title' => 'Alumni Management',
                'description' => 'Alumni records, career tracking, events, engagement and donations',
                'parent_menu_id' => 0,
                'level' => 1,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::MODULE_LINK,
                'icon' => 'mdi mdi-school-outline',
                'sub_institute_id' => $subInstituteId,
                'client_id' => $clientId,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'Alumni Management',
                'menu_path' => 'Alumni Management',
                'created_at' => now(),
            ]);
            $module = DB::table('tblmenumaster')->where('id', $moduleId)->first();
        }

        $menuSort = 0;
        foreach (self::MENUS as $menu) {
            $menuSort++;

            if (DB::table('tblmenumaster')->where('link', $menu['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $menu['name'],
                'menu_title' => 'Alumni Management',
                'description' => $menu['description'],
                'parent_menu_id' => $module->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => $menuSort,
                'link' => $menu['link'],
                'icon' => 'mdi mdi-school-outline',
                'sub_institute_id' => $module->sub_institute_id,
                'client_id' => $module->client_id,
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
        foreach (self::MENUS as $menu) {
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
        // Union of the "Institute ERP" module's list (every provisioned institute), school_setup
        // and school_detail. school_detail alone omits some institutes, which then never see the
        // menu (the menu API filters with FIND_IN_SET on this column).
        $ids = [];
        $ref = DB::table('tblmenumaster')->where('level', 1)->where('name', 'Institute ERP')->value('sub_institute_id');
        foreach (explode(',', (string) $ref) as $v) {
            $ids[] = (int) trim($v);
        }
        foreach (['school_setup' => 'Id', 'school_detail' => 'id'] as $table => $col) {
            if (Schema::hasTable($table)) {
                foreach (DB::table($table)->pluck($col) as $v) {
                    $ids[] = (int) $v;
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));
        sort($ids);

        return $ids === [] ? '1' : implode(',', $ids);
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
