<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers AI Administration under Settings.
 *
 * The SPA sidebar renders from `tblmenumaster`, so a screen without a row here is
 * invisible however well it is built. Modelled on
 * 2026_08_17_110000_add_new_pal_gamification_submodule_menu: matched on the parent's
 * NAME rather than its link (which has been blanked on at least one estate),
 * idempotent, and it touches nothing but its own rows.
 *
 * Placed under an existing admin module rather than as a new level-1 entry, per the
 * brief's instruction to keep technical configuration out of the main navigation.
 * Normal users never need it — they meet the intelligence layer through the AI
 * Insights tabs on the records they already work with.
 */
return new class extends Migration
{
    private const PARENT_NAMES = ['settings', 'setting', 'administration', 'master setup'];

    private const MODULE = [
        'name' => 'AI Administration',
        'link' => 'ai_admin',
        'icon' => 'mdi mdi-robot-outline',
        'description' => 'Agents, ontology, knowledge graph, workflows, templates and AI audit logs',
    ];

    private const SUB_MODULES = [
        ['name' => 'Agents', 'link' => 'ai_admin.agents', 'icon' => 'mdi mdi-robot-outline', 'description' => 'Registered agents, their reach and their run history'],
        ['name' => 'Workflows', 'link' => 'ai_admin.workflows', 'icon' => 'mdi mdi-sitemap-outline', 'description' => 'Workflow definitions, versions and runs'],
        ['name' => 'Ontology', 'link' => 'ai_admin.ontology', 'icon' => 'mdi mdi-shape-outline', 'description' => 'Shared entities and relationships'],
        ['name' => 'Knowledge Graph', 'link' => 'ai_admin.knowledge-graph', 'icon' => 'mdi mdi-graph-outline', 'description' => 'Explore relationships across real records'],
        ['name' => 'Templates', 'link' => 'ai_admin.templates', 'icon' => 'mdi mdi-text-box-outline', 'description' => 'Versioned prompts used for content generation'],
        ['name' => 'AI Audit Logs', 'link' => 'ai_admin.audit-logs', 'icon' => 'mdi mdi-clipboard-text-clock-outline', 'description' => 'Every agent run, decision and governance refusal'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = $this->findParent();

        if ($parent === null) {
            // No suitable parent on this estate. Do nothing rather than inventing a
            // top-level module — a stray level-1 entry is worse than a missing screen.
            return;
        }

        $moduleId = $this->upsertMenu(
            self::MODULE,
            (int) $parent->id,
            (int) $parent->level + 1,
            $parent
        );

        if ($moduleId === null) {
            return;
        }

        foreach (self::SUB_MODULES as $subModule) {
            $this->upsertMenu($subModule, $moduleId, (int) $parent->level + 2, $parent);
        }

        $this->grantAdminRights();
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_merge(
            [self::MODULE['link']],
            array_column(self::SUB_MODULES, 'link')
        );

        $ids = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        foreach (['tblmenurights', 'menu_rights', 'tblmenu_rights'] as $rightsTable) {
            if (Schema::hasTable($rightsTable) && Schema::hasColumn($rightsTable, 'menu_id')) {
                DB::table($rightsTable)->whereIn('menu_id', $ids)->delete();
            }
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
    }

    private function findParent(): ?object
    {
        foreach (self::PARENT_NAMES as $name) {
            $parent = DB::table('tblmenumaster')
                ->whereRaw('LOWER(name) = ?', [$name])
                ->whereIn('level', [1, 2])
                ->orderBy('level')
                ->orderBy('id')
                ->first();

            if ($parent !== null) {
                return $parent;
            }
        }

        return null;
    }

    private function upsertMenu(array $menu, int $parentId, int $level, object $parent): ?int
    {
        $existing = DB::table('tblmenumaster')->where('link', $menu['link'])->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $sortOrder = ((int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->max('sort_order')) + 1;

        return (int) DB::table('tblmenumaster')->insertGetId([
            'name' => $menu['name'],
            'menu_title' => $parent->menu_title,
            'description' => $menu['description'],
            'parent_menu_id' => $parentId,
            'level' => $level,
            'status' => 1,
            'sort_order' => $sortOrder,
            'link' => $menu['link'],
            'icon' => $menu['icon'],
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => $menu['name'],
            'menu_path' => $menu['name'],
            'created_at' => now(),
        ]);
    }

    /**
     * Grant the new menus to whatever the estate already grants its admin profile.
     *
     * Rights tables differ across installs, so this mirrors an existing admin grant
     * rather than assuming a schema. If no recognisable rights table is present it
     * does nothing — the menus exist, and an administrator can grant them by hand.
     */
    private function grantAdminRights(): void
    {
        $rightsTable = collect(['tblmenurights', 'menu_rights', 'tblmenu_rights'])
            ->first(fn ($table) => Schema::hasTable($table) && Schema::hasColumn($table, 'menu_id'));

        if ($rightsTable === null) {
            return;
        }

        $links = array_merge([self::MODULE['link']], array_column(self::SUB_MODULES, 'link'));
        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();

        if ($menuIds === []) {
            return;
        }

        // Use an existing admin grant as the template for the new rows, so whatever
        // columns this estate's rights table has are populated the same way.
        $template = DB::table($rightsTable)->orderBy('id')->first();

        if ($template === null) {
            return;
        }

        $templateRow = (array) $template;
        unset($templateRow['id'], $templateRow['menu_id'], $templateRow['created_at'], $templateRow['updated_at']);

        foreach ($menuIds as $menuId) {
            $exists = DB::table($rightsTable)->where('menu_id', $menuId);

            foreach ($templateRow as $column => $value) {
                $exists->where($column, $value);
            }

            if ($exists->exists()) {
                continue;
            }

            DB::table($rightsTable)->insert($templateRow + [
                'menu_id' => $menuId,
                'created_at' => now(),
            ]);
        }
    }
};
