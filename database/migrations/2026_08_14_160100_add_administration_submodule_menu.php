<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Administration" as a level-3 sub-module of New PAL.
 *
 * Deliberately the SAME SHAPE as 2026_08_14_140000_add_new_pal_submodule_menu,
 * which established this pattern for Content Model. Matching it matters:
 *
 *   - the link follows the `new_pal.<sub_module>` convention already in the
 *     table (`new_pal.content_model`, id 532), not a separate `*.index` root,
 *     so the level-3 rows under New PAL stay a consistent family;
 *   - rights are MIRRORED FROM THE PARENT rather than derived from siblings,
 *     so Administration can never be visible to somebody who cannot see the
 *     module that contains it, and it does not depend on Content Model's own
 *     migration having run first (it lives on a branch this one may not have);
 *   - the parent is matched on link then name, because the link is editable
 *     from the menu admin screens and was blanked once already — see the
 *     `repair_new_pal_menu_link_and_submodules` migration.
 *
 * Timestamped 160100 rather than 14/15xxxx because those slots are already
 * taken on the deployed estate (add_new_pal_submodule_menu,
 * seed_pal_h5p_model_registry, create_pal_h5p_node_metadata_table,
 * repair_new_pal_menu_link_and_submodules). A duplicate timestamp still runs,
 * but the resulting order is decided by filename rather than intent.
 *
 * Idempotent. Additive: no existing row is altered.
 */
return new class extends Migration
{
    private const PARENT_LINK = 'new_pal.index';

    private const SUB_MODULE = [
        'name' => 'Administration',
        'link' => 'new_pal.administration',
        'icon' => 'mdi mdi-tune-variant',
        'description' => 'PAL V4 architecture control plane — intelligence layers, adaptive loop, mastery model, HPC stages, progression rubric, knowledge graph, AI agents, student model and career pathway',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = $this->resolveParent();
        if ($parent === null) {
            // No New PAL row on this estate. Stay green so a fresh install is
            // not blocked; the route still works, it just has no sidebar entry.
            return;
        }

        if (DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->exists()) {
            return;
        }

        $sortOrder = (int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order');

        $menuId = DB::table('tblmenumaster')->insertGetId([
            'name' => self::SUB_MODULE['name'],
            'menu_title' => $parent->menu_title,
            'description' => self::SUB_MODULE['description'],
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => $sortOrder + 1,
            'link' => self::SUB_MODULE['link'],
            'icon' => self::SUB_MODULE['icon'],
            // Inherited, not restated: scoped to exactly the institutes and
            // clients the module itself is scoped to.
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => self::SUB_MODULE['name'],
            'menu_path' => self::SUB_MODULE['name'],
            'created_at' => now(),
        ]);

        $this->mirrorRights((int) $parent->id, $menuId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->value('id');
        if ($menuId === null) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
        }

        DB::table('tblmenumaster')->where('id', $menuId)->delete();
    }

    /**
     * The New PAL menu row. Matched on link first, then on name — a blanked
     * link must not silently turn this migration into a no-op.
     */
    private function resolveParent(): ?object
    {
        $parent = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->first();
        if ($parent !== null) {
            return $parent;
        }

        return DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['new pal'])
            ->where('level', 2)
            ->where('status', 1)
            ->first();
    }

    /**
     * Give the sub-module exactly the grants its parent module has.
     *
     * The view/add/edit flags are copied verbatim rather than forced, so the
     * row matches its siblings. Write AUTHORITY for architecture settings is
     * not taken from here in any case — PalArchitectureController decides it
     * per request via ArchitectureRegistry::mayWrite.
     */
    private function mirrorRights(int $parentMenuId, int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $grants = DB::table('tblgroupwise_rights')->where('menu_id', $parentMenuId)->get();

        foreach ($grants as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                'can_view' => $grant->can_view,
                'can_add' => $grant->can_add,
                'can_edit' => $grant->can_edit,
                'can_delete' => $grant->can_delete,
                'created_at' => now(),
            ]);
        }
    }
};
