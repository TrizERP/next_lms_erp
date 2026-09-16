<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Makes the Fees / Teach/Learn category menus visible to the people who can
 * already see the module they belong to.
 *
 * 2026_09_16_100000_seed_module_category_menus_in_tblmenumaster added the
 * category rows, but a `tblmenumaster` row on its own renders for nobody:
 * MenuRightsController::getMenuRightsLevelWise inner-joins the menu table
 * against `tblindividual_rights` (per user) OR `tblgroupwise_rights` (per
 * profile). No rights row, no tile. (`tblprofilewise_menu` looks like the
 * rights table and is never read by that query -- do not grant there.)
 *
 * So each category menu inherits, verbatim, the grants held on its parent
 * module menu: Fees = tblmenumaster.id 6, Teach/Learn = 269. Same profiles,
 * same users, same tenants, same can_view/add/edit/delete flags. Nobody gains
 * access to a category who could not already open the module.
 *
 * Insert-only: a grant is written only where that exact
 * (menu, profile/user, tenant) combination is absent, so re-running never
 * duplicates and never overwrites a right somebody has since edited by hand.
 */
return new class extends Migration
{
    /** Source module menu => how its category menus were labelled. */
    private const MODULES = [
        'fees' => ['menu_id' => 6, 'menu_title' => 'Fees'],
        'teach_learn' => ['menu_id' => 269, 'menu_title' => 'Teach/Learn'],
    ];

    private const CHUNK = 500;

    public function up(): void
    {
        foreach (self::MODULES as $moduleName => $module) {
            $targetMenuIds = $this->categoryMenuIds($moduleName, $module);

            if (!$targetMenuIds) {
                continue;
            }

            $this->mirrorGroupwise($module['menu_id'], $targetMenuIds);
            $this->mirrorIndividual($module['menu_id'], $targetMenuIds);
        }
    }

    public function down(): void
    {
        foreach (self::MODULES as $moduleName => $module) {
            $targetMenuIds = $this->categoryMenuIds($moduleName, $module);

            if (!$targetMenuIds) {
                continue;
            }

            DB::table('tblgroupwise_rights')->whereIn('menu_id', $targetMenuIds)->delete();
            DB::table('tblindividual_rights')->whereIn('menu_id', $targetMenuIds)->delete();
        }
    }

    /**
     * The category menus for one module, identified the same way the seeding
     * migration identifies them, so the two stay in step without hardcoded ids.
     */
    private function categoryMenuIds(string $moduleName, array $module): array
    {
        $moduleMenu = DB::table('tblmenumaster')->where('id', $module['menu_id'])->first();

        if (!$moduleMenu) {
            return [];
        }

        $links = DB::table('fees_menu_categories')
            ->where('module_name', $moduleName)
            ->pluck('route')
            ->map(fn ($route) => trim((string) $route))
            ->filter()
            ->all();

        if (!$links) {
            return [];
        }

        return DB::table('tblmenumaster')
            ->where('parent_menu_id', (int) $moduleMenu->parent_menu_id)
            ->where('level', 2)
            ->where('menu_title', $module['menu_title'])
            ->whereIn('link', $links)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function mirrorGroupwise(int $sourceMenuId, array $targetMenuIds): void
    {
        $sources = DB::table('tblgroupwise_rights')->where('menu_id', $sourceMenuId)->get();

        if ($sources->isEmpty()) {
            return;
        }

        // One read of what is already granted, rather than a query per row.
        $existing = $this->existingKeys(
            'tblgroupwise_rights',
            ['menu_id', 'profile_id', 'sub_institute_id'],
            $targetMenuIds
        );

        $pending = [];

        foreach ($targetMenuIds as $menuId) {
            foreach ($sources as $source) {
                $key = $this->key([$menuId, $source->profile_id, $source->sub_institute_id]);

                if (isset($existing[$key])) {
                    continue;
                }
                $existing[$key] = true;

                $pending[] = [
                    'menu_id' => $menuId,
                    'profile_id' => $source->profile_id,
                    'can_view' => $source->can_view,
                    'can_add' => $source->can_add,
                    'can_edit' => $source->can_edit,
                    'can_delete' => $source->can_delete,
                    'dashboard_right' => $source->dashboard_right,
                    'sub_institute_id' => $source->sub_institute_id,
                    'sort_order' => $source->sort_order,
                    'is_mobile' => $source->is_mobile,
                    'created_at' => now(),
                ];
            }
        }

        $this->insertChunked('tblgroupwise_rights', $pending);
    }

    private function mirrorIndividual(int $sourceMenuId, array $targetMenuIds): void
    {
        $sources = DB::table('tblindividual_rights')->where('menu_id', $sourceMenuId)->get();

        if ($sources->isEmpty()) {
            return;
        }

        $existing = $this->existingKeys(
            'tblindividual_rights',
            ['menu_id', 'user_id', 'profile_id', 'sub_institute_id'],
            $targetMenuIds
        );

        $pending = [];

        foreach ($targetMenuIds as $menuId) {
            foreach ($sources as $source) {
                $key = $this->key([$menuId, $source->user_id, $source->profile_id, $source->sub_institute_id]);

                if (isset($existing[$key])) {
                    continue;
                }
                $existing[$key] = true;

                $pending[] = [
                    'user_id' => $source->user_id,
                    'menu_id' => $menuId,
                    'profile_id' => $source->profile_id,
                    'can_view' => $source->can_view,
                    'can_add' => $source->can_add,
                    'can_edit' => $source->can_edit,
                    'can_delete' => $source->can_delete,
                    'sub_institute_id' => $source->sub_institute_id,
                    'client_id' => $source->client_id,
                    'is_mobile' => $source->is_mobile,
                    'created_at' => now(),
                ];
            }
        }

        $this->insertChunked('tblindividual_rights', $pending);
    }

    private function existingKeys(string $table, array $columns, array $targetMenuIds): array
    {
        $keys = [];

        DB::table($table)
            ->select(array_merge(['id'], $columns))
            ->whereIn('menu_id', $targetMenuIds)
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$keys, $columns) {
                foreach ($rows as $row) {
                    $parts = [];
                    foreach ($columns as $column) {
                        $parts[] = $row->$column;
                    }
                    $keys[$this->key($parts)] = true;
                }
            });

        return $keys;
    }

    /** Null and '' must not collide with a real id, hence the explicit marker. */
    private function key(array $parts): string
    {
        return implode('|', array_map(
            fn ($part) => $part === null ? '~' : (string) $part,
            $parts
        ));
    }

    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
};
