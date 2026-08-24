<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Capability Intelligence" as a new top-level (level 1) sidebar
 * module, with its 5 menus (level 2), for the as-is migration of G2G's
 * Capability Intelligence module (Dashboard, Competency Library, Capability
 * Library, Competency Framework, Capability Explorer) to the Next.js
 * frontend (see app/capability-intelligence/* in the lms_k12 repo).
 *
 * Every item here is a fresh Next.js-only surface with no legacy screen to
 * collide with, so all 5 links use fresh `capability_intelligence.*` slugs
 * — flat, no level=3 nesting, since none of the 5 items have their own
 * children.
 *
 * sub_institute_id: same reasoning as
 * 2026_08_18_120000_add_talent_management_menu.php — this is also a
 * brand-new, parent-less top-level module, so every currently-provisioned
 * institute is enumerated from `school_detail` at migration time rather
 * than copied from a parent row.
 *
 * Idempotent: safe to re-run: existing rows (matched by `link`) are left
 * untouched rather than duplicated.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'capability_intelligence.index';

    private const MENUS = [
        ['name' => 'Dashboard', 'link' => 'capability_intelligence.dashboard', 'description' => 'Capability Intelligence: competencies and capabilities overview'],
        ['name' => 'Competency Library', 'link' => 'capability_intelligence.competency_library', 'description' => 'Master list of defined competencies'],
        ['name' => 'Capability Library', 'link' => 'capability_intelligence.capability_library', 'description' => 'Master list of defined capabilities'],
        ['name' => 'Competency Framework', 'link' => 'capability_intelligence.competency_framework', 'description' => 'Competency frameworks and proficiency levels'],
        ['name' => 'Capability Explorer', 'link' => 'capability_intelligence.capability_explorer', 'description' => 'Explore and analyse capabilities across the organisation'],
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
                'name' => 'Capability Intelligence',
                'menu_title' => 'Capability Intelligence',
                'description' => 'Competency and capability libraries, frameworks and exploration',
                'parent_menu_id' => 0,
                'level' => 1,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::MODULE_LINK,
                'icon' => 'mdi mdi-brain',
                'sub_institute_id' => $subInstituteId,
                'client_id' => $clientId,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'Capability Intelligence',
                'menu_path' => 'Capability Intelligence',
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
                'menu_title' => 'Capability Intelligence',
                'description' => $menu['description'],
                'parent_menu_id' => $module->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => $menuSort,
                'link' => $menu['link'],
                'icon' => 'mdi mdi-brain',
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
