<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers AI & Intelligence as a real level-1 module with twelve submodules.
 *
 * WHY THIS EXISTS
 *
 * The twelve capabilities were reachable only from the avatar dropdown, which is a
 * hard-coded list in the SPA. The sidebar renders from `tblmenumaster`, so without
 * rows here they are not part of the application's navigation at all: they cannot be
 * granted per profile, cannot be searched, cannot be reordered, and do not appear in
 * the level-2 panel the rest of the product uses.
 *
 * WHAT IT CORRECTS
 *
 * `2026_08_20_000010_add_ai_administration_menu` already inserted seven `ai_admin`
 * rows, and both halves of that migration missed on this estate:
 *
 *   1. Its `grantAdminRights()` looked for `tblmenurights`, `menu_rights` or
 *      `tblmenu_rights`. This estate's rights table is `tblgroupwise_rights`, so the
 *      helper returned early and granted nothing. Menus 574-580 carry zero rights
 *      rows and are invisible to every profile.
 *   2. It landed under Talent Management (563 → People & Competency), not under a
 *      settings-like parent as intended.
 *
 * Those rows are deliberately left alone here — see `down()` and the note below.
 * This migration adds its own module rather than repairing theirs, because the two
 * describe different things: `ai_admin` is the operational console for agents and
 * ontology, this is the platform's AI capability catalogue.
 *
 * WHO GETS IT
 *
 * Every active profile on the estate — 585 of them across 92 sub-institutes, which is
 * ~7,605 `tblgroupwise_rights` rows. That is the decision on record: the module is to
 * be visible to every user who logs in, not only to administrators.
 *
 * Worth knowing rather than assumed: those profiles include Student (88), Parent (29),
 * Librarian, Clerk and Peon. Granting to all of them puts "AI Providers", "Prompt
 * Management" and "AI Audit" in a student's and a parent's sidebar. The grant is
 * view-only, so nobody can change anything, but the entries are visible. Narrow it
 * later by deleting rows for the profile names that should not have it — the menu ids
 * are stable, so that is a targeted delete rather than a re-run.
 *
 * `AI_MENU_GRANT_SUB_INSTITUTES` narrows the scope when you want a smaller blast
 * radius:
 *
 *   (unset)   every active profile, every sub-institute. The default, as decided.
 *   47,48     only profiles belonging to those sub-institutes.
 *   none      insert the menus and grant nothing — they stay invisible.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_10_000001_add_ai_intelligence_menu.php
 */
return new class extends Migration
{
    private const MODULE = [
        'name' => 'AI & Intelligence',
        'link' => 'ai_intelligence',
        'icon' => 'brain',
        'description' => 'The AI capabilities the platform provides once and every module calls.',
    ];

    /**
     * The twelve submodules, in the order the capability registry lists them.
     *
     * `link` is what `mapApiLinkToRoute()` in the SPA resolves, and it is built from
     * the same slugs `packages/ai-intelligence-core` uses — so the sidebar entry and
     * the avatar dropdown open the same screen, and a renamed capability is one edit
     * in the registry plus one row here.
     */
    private const SUB_MODULES = [
        ['name' => 'AI Providers',           'slug' => 'providers',         'icon' => 'mdi mdi-server-network'],
        ['name' => 'Model Management',       'slug' => 'models',            'icon' => 'mdi mdi-cube-outline'],
        ['name' => 'Prompt Management',      'slug' => 'prompts',           'icon' => 'mdi mdi-text-box-outline'],
        ['name' => 'AI Policies',            'slug' => 'policies',          'icon' => 'mdi mdi-shield-check-outline'],
        ['name' => 'Agent Management',       'slug' => 'agents',            'icon' => 'mdi mdi-robot-outline'],
        ['name' => 'Conversational AI',      'slug' => 'conversational-ai', 'icon' => 'mdi mdi-message-text-outline'],
        ['name' => 'Knowledge & RAG',        'slug' => 'knowledge-rag',     'icon' => 'mdi mdi-book-open-variant'],
        ['name' => 'Recommendation Engine',  'slug' => 'recommendations',   'icon' => 'mdi mdi-lightbulb-on-outline'],
        ['name' => 'Knowledge Graph',        'slug' => 'knowledge-graph',   'icon' => 'mdi mdi-graph-outline'],
        ['name' => 'AI Evaluation',          'slug' => 'evaluation',        'icon' => 'mdi mdi-check-decagram-outline'],
        ['name' => 'Usage & Cost',           'slug' => 'usage-cost',        'icon' => 'mdi mdi-chart-line'],
        ['name' => 'AI Audit',               'slug' => 'audit',             'icon' => 'mdi mdi-clipboard-text-clock-outline'],
    ];

    /** The level-1 row whose estate scope (`sub_institute_id`, `client_id`) is copied. */
    private const SCOPE_TEMPLATE_ID = 1; // Institute ERP

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $template = DB::table('tblmenumaster')->where('id', self::SCOPE_TEMPLATE_ID)->first();

        if ($template === null) {
            // Without a template we would have to invent the estate scope, and a
            // module scoped to the wrong sub-institutes is worse than none.
            return;
        }

        $moduleId = $this->upsertModule($template);

        foreach (self::SUB_MODULES as $index => $subModule) {
            $this->upsertSubModule($subModule, $moduleId, $index + 1, $template);
        }

        $this->grantRights();
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_merge(
            [self::MODULE['link']],
            array_map(fn ($sub) => 'ai_intelligence.' . $sub['slug'], self::SUB_MODULES)
        );

        $ids = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        // Rights first, so a half-finished rollback never leaves a grant pointing at
        // a menu id that has been deleted and may later be reissued to another row.
        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $ids)->delete();
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
    }

    private function upsertModule(object $template): int
    {
        $existing = DB::table('tblmenumaster')->where('link', self::MODULE['link'])->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $sortOrder = ((int) DB::table('tblmenumaster')->where('parent_menu_id', 0)->max('sort_order')) + 1;

        return (int) DB::table('tblmenumaster')->insertGetId([
            'name' => self::MODULE['name'],
            'description' => self::MODULE['description'],
            'parent_menu_id' => 0,
            'level' => 1,
            'status' => 1,
            'sort_order' => $sortOrder,
            // Must be MODULE['link']: the existence check above and down() both
            // find this row by it. An earlier version wrote 'javascript:void(0);'
            // here — the conventional level-1 container link — which made the
            // migration non-idempotent, because the lookup could never match what
            // was written and a second run would insert a second level-1 module.
            // Navigation is unaffected: Sidebar's level-1 handler calls
            // preventDefault() and opens the level-2 panel, so a level-1 link is
            // never followed. routeMapper resolves this one to /ai regardless.
            'link' => self::MODULE['link'],
            'icon' => self::MODULE['icon'],
            'sub_institute_id' => $template->sub_institute_id,
            'client_id' => $template->client_id,
            'site_map_name' => self::MODULE['name'],
            'menu_path' => self::MODULE['name'],
            'created_at' => now(),
        ]);
    }

    private function upsertSubModule(array $subModule, int $parentId, int $sortOrder, object $template): void
    {
        $link = 'ai_intelligence.' . $subModule['slug'];

        if (DB::table('tblmenumaster')->where('link', $link)->exists()) {
            return;
        }

        DB::table('tblmenumaster')->insert([
            'name' => $subModule['name'],
            'menu_title' => self::MODULE['name'],
            'description' => $subModule['name'],
            'parent_menu_id' => $parentId,
            'level' => 2,
            'status' => 1,
            'sort_order' => $sortOrder,
            'link' => $link,
            'icon' => $subModule['icon'],
            'sub_institute_id' => $template->sub_institute_id,
            'client_id' => $template->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => $subModule['name'],
            'menu_path' => self::MODULE['name'] . ' / ' . $subModule['name'],
            'created_at' => now(),
        ]);
    }

    /**
     * Grant the new menus to every active profile, within the configured scope.
     *
     * WHY THIS IS BATCHED
     *
     * 585 profiles × 13 menus is 7,605 rows. Written the obvious way — an `exists()`
     * then an `insert()` per row — that is over 15,000 round trips to a remote MySQL
     * host, which takes long enough that the migration looks hung and someone kills it
     * half-applied. Existing pairs are read once into a set and the new rows go in
     * chunks instead, which is ~16 statements.
     */
    private function grantRights(): void
    {
        $scope = strtolower(trim((string) env('AI_MENU_GRANT_SUB_INSTITUTES', '')));

        if ($scope === 'none' || ! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
            return;
        }

        $links = array_merge(
            [self::MODULE['link']],
            array_map(fn ($sub) => 'ai_intelligence.' . $sub['slug'], self::SUB_MODULES)
        );

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();

        if ($menuIds === []) {
            return;
        }

        $profiles = DB::table('tbluserprofilemaster')->where('status', 1);

        if ($scope !== '' && $scope !== 'all') {
            $subInstitutes = array_filter(array_map('trim', explode(',', $scope)), fn ($id) => $id !== '');

            if ($subInstitutes === []) {
                return;
            }

            $profiles->whereIn('sub_institute_id', $subInstitutes);
        }

        // Re-running must not double-grant, so what is already there is read once and
        // used to skip, rather than checked row by row.
        $existing = [];
        DB::table('tblgroupwise_rights')
            ->whereIn('menu_id', $menuIds)
            ->select('menu_id', 'profile_id', 'sub_institute_id')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$existing) {
                foreach ($rows as $row) {
                    $existing[$row->menu_id . '|' . $row->profile_id . '|' . $row->sub_institute_id] = true;
                }
            });

        $now = now();
        $pending = [];

        foreach ($profiles->get(['id', 'sub_institute_id']) as $profile) {
            foreach ($menuIds as $menuId) {
                if (isset($existing[$menuId . '|' . $profile->id . '|' . $profile->sub_institute_id])) {
                    continue;
                }

                $pending[] = [
                    'menu_id' => $menuId,
                    'profile_id' => $profile->id,
                    'sub_institute_id' => $profile->sub_institute_id,
                    'can_view' => 1,
                    // Read-only: these screens describe and configure platform
                    // services. Nothing on them creates or deletes a tenant record,
                    // so granting add/edit/delete would overstate what they do — and
                    // this grant reaches student and parent profiles.
                    'can_add' => 0,
                    'can_edit' => 0,
                    'can_delete' => 0,
                    'created_at' => $now,
                ];
            }
        }

        foreach (array_chunk($pending, 500) as $chunk) {
            DB::table('tblgroupwise_rights')->insert($chunk);
        }
    }
};
