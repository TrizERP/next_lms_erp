<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Library menu regrouping (Institute ERP, tblmenumaster id 1).
 *
 * Before: three separate Level 2 menus - Books (359), Stock Verification
 * (467) and Library Report (404) - each with its own category bar.
 *
 * After: ONE Level 2 menu, "Library", whose category bar (the existing
 * level-3 bar mechanism, fees_menu_categories / fees_menu_category_items,
 * module_name 'library') is:
 *
 *   Operations -> Books pages, then Stock Verification pages
 *   Reports    -> Library Report pages
 *
 * tblmenumaster only has three renderable levels (module -> Level 2 -> Level
 * 3 screen), so Operations/Reports are categories, not tblmenumaster rows -
 * the same shape Fees and every other module already use.
 *
 * What it does:
 *  - creates the Level 2 "Library" row (copying tenant/client scope and icon
 *    from "Books"), or reuses it if it already exists;
 *  - re-parents the ten existing Level 3 screens to it (ids unchanged, so
 *    every existing right on a screen keeps working);
 *  - copies every right held on the three old Level 2 menus onto the new one
 *    (group rights, individual rights, profile-wise menu), so everyone who
 *    could see any of the three sees Library;
 *  - hides (status = 0, not deleted) the three old Level 2 menus;
 *  - seeds the Library category bar (Operations, Reports) and points the
 *    pre-existing 'library' AI Stack category at the new menu.
 *
 * Idempotent, and down() restores the previous state.
 */
return new class extends Migration
{
    private const OLD_LEVEL2 = [
        'books' => 359,
        'stock' => 467,
        'report' => 404,
    ];

    /** old Level 2 id => its Level 3 screen ids, in display order. */
    private const SCREENS = [
        359 => [360, 407, 409],
        467 => [468, 469],
        404 => [405, 408, 470, 471, 491],
    ];

    private const PARENT_ID = 1; // Institute ERP
    private const SLUG = 'library';

    public function up(): void
    {
        DB::transaction(function () {
            $libraryId = $this->ensureLibraryMenu();

            // Re-parent the Level 3 screens.
            foreach (self::SCREENS as $screens) {
                DB::table('tblmenumaster')->whereIn('id', $screens)->update([
                    'parent_menu_id' => $libraryId,
                    'updated_at' => now(),
                ]);
            }

            $this->copyRights($libraryId);

            // Hide (not delete) the three old Level 2 menus.
            DB::table('tblmenumaster')->whereIn('id', array_values(self::OLD_LEVEL2))->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

            $this->seedCategoryBar($libraryId);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $libraryId = DB::table('tblmenumaster')
                ->where('parent_menu_id', self::PARENT_ID)->where('level', 2)->where('name', 'Library')
                ->whereNotIn('id', array_values(self::OLD_LEVEL2))->value('id');

            foreach (self::SCREENS as $oldId => $screens) {
                DB::table('tblmenumaster')->whereIn('id', $screens)->update([
                    'parent_menu_id' => $oldId,
                    'updated_at' => now(),
                ]);
            }

            DB::table('tblmenumaster')->whereIn('id', array_values(self::OLD_LEVEL2))->update([
                'status' => 1,
                'updated_at' => now(),
            ]);

            if ($libraryId) {
                DB::table('tblgroupwise_rights')->where('menu_id', $libraryId)->delete();
                DB::table('tblindividual_rights')->where('menu_id', $libraryId)->delete();
                DB::table('tblprofilewise_menu')->where('menu_id', $libraryId)->delete();

                DB::table('fees_menu_category_items')->where('module_name', self::SLUG)->delete();
                // The AI Stack row predates this migration (it pointed at Books); keep it, re-point it back.
                DB::table('fees_menu_categories')->where('module_name', self::SLUG)
                    ->where('category_key', 'ai-stack')->update(['level2_menu_id' => self::OLD_LEVEL2['books']]);
                DB::table('fees_menu_categories')->where('module_name', self::SLUG)
                    ->where('category_key', '!=', 'ai-stack')->delete();

                DB::table('tblmenumaster')->where('id', $libraryId)->delete();
            }
        });
    }

    private function ensureLibraryMenu(): int
    {
        $existing = DB::table('tblmenumaster')
            ->where('parent_menu_id', self::PARENT_ID)->where('level', 2)->where('name', 'Library')
            ->whereNotIn('id', array_values(self::OLD_LEVEL2))->value('id');
        if ($existing) {
            DB::table('tblmenumaster')->where('id', $existing)->update(['status' => 1]);
            return (int) $existing;
        }

        // Scope (tenants/clients) is the union of the three old menus, so nobody loses access.
        $rows = DB::table('tblmenumaster')->whereIn('id', array_values(self::OLD_LEVEL2))->get();
        $union = fn (string $col) => collect($rows)
            ->flatMap(fn ($r) => explode(',', (string) $r->{$col}))
            ->map(fn ($v) => trim($v))->filter(fn ($v) => $v !== '')->unique()->implode(',');

        $template = (array) $rows->firstWhere('id', self::OLD_LEVEL2['books']);
        unset($template['id']);

        return (int) DB::table('tblmenumaster')->insertGetId(array_merge($template, [
            'name' => 'Library',
            'menu_title' => 'Library',
            'description' => 'Library',
            'site_map_name' => 'Library',
            'menu_path' => 'Library',
            'parent_menu_id' => self::PARENT_ID,
            'level' => 2,
            'status' => 1,
            'sort_order' => 1,
            'link' => 'javascript:void(0);',
            'icon' => 'mdi mdi-library',
            'sub_institute_id' => $union('sub_institute_id'),
            'client_id' => $union('client_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /** Give the new menu every right any of the three old menus carried. */
    private function copyRights(int $libraryId): void
    {
        $old = array_values(self::OLD_LEVEL2);

        $group = DB::table('tblgroupwise_rights')->whereIn('menu_id', $old)->get()
            ->groupBy(fn ($r) => $r->profile_id.'|'.$r->sub_institute_id);
        foreach ($group as $rows) {
            $first = $rows->first();
            $exists = DB::table('tblgroupwise_rights')->where('menu_id', $libraryId)
                ->where('profile_id', $first->profile_id)->where('sub_institute_id', $first->sub_institute_id)->exists();
            if ($exists) {
                continue;
            }
            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $libraryId,
                'profile_id' => $first->profile_id,
                'can_view' => (int) $rows->max('can_view'),
                'can_add' => (int) $rows->max('can_add'),
                'can_edit' => (int) $rows->max('can_edit'),
                'can_delete' => (int) $rows->max('can_delete'),
                'dashboard_right' => $first->dashboard_right,
                'created_at' => now(),
                'sub_institute_id' => $first->sub_institute_id,
                'sort_order' => $first->sort_order,
                'is_mobile' => (int) $rows->max('is_mobile'),
            ]);
        }

        $indiv = DB::table('tblindividual_rights')->whereIn('menu_id', $old)->get()
            ->groupBy(fn ($r) => $r->user_id.'|'.$r->profile_id.'|'.$r->sub_institute_id);
        foreach ($indiv as $rows) {
            $first = $rows->first();
            $exists = DB::table('tblindividual_rights')->where('menu_id', $libraryId)->where('user_id', $first->user_id)
                ->where('profile_id', $first->profile_id)->where('sub_institute_id', $first->sub_institute_id)->exists();
            if ($exists) {
                continue;
            }
            DB::table('tblindividual_rights')->insert([
                'user_id' => $first->user_id,
                'menu_id' => $libraryId,
                'profile_id' => $first->profile_id,
                'can_view' => (int) $rows->max('can_view'),
                'can_add' => (int) $rows->max('can_add'),
                'can_edit' => (int) $rows->max('can_edit'),
                'can_delete' => (int) $rows->max('can_delete'),
                'created_at' => now(),
                'sub_institute_id' => $first->sub_institute_id,
                'client_id' => $first->client_id,
                'is_mobile' => (int) $rows->max('is_mobile'),
            ]);
        }

        $profile = DB::table('tblprofilewise_menu')->whereIn('menu_id', $old)->get()
            ->groupBy(fn ($r) => $r->user_profile_id.'|'.$r->sub_institute_id);
        foreach ($profile as $rows) {
            $first = $rows->first();
            $exists = DB::table('tblprofilewise_menu')->where('menu_id', $libraryId)
                ->where('user_profile_id', $first->user_profile_id)->where('sub_institute_id', $first->sub_institute_id)->exists();
            if ($exists) {
                continue;
            }
            DB::table('tblprofilewise_menu')->insert([
                'menu_id' => $libraryId,
                'user_profile_id' => $first->user_profile_id,
                'sub_institute_id' => $first->sub_institute_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCategoryBar(int $libraryId): void
    {
        $now = now();

        $categories = [
            'operations' => ['Operations', 'Day-to-day Library screens.', 4],
            'reports' => ['Reports', 'Library reporting and analysis.', 5],
        ];
        foreach ($categories as $key => [$label, $description, $sort]) {
            $exists = DB::table('fees_menu_categories')->where('module_name', self::SLUG)->where('category_key', $key)->exists();
            if (! $exists) {
                DB::table('fees_menu_categories')->insert([
                    'module_name' => self::SLUG,
                    'level2_menu_id' => $libraryId,
                    'category_key' => $key,
                    'label' => $label,
                    'description' => $description,
                    'route' => '/modules/'.self::SLUG.'/'.$key,
                    'sort_order' => $sort,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        // Whatever 'library' categories already exist (the AI Stack row) belong to the new menu.
        DB::table('fees_menu_categories')->where('module_name', self::SLUG)->update(['level2_menu_id' => $libraryId]);

        $items = [
            'operations' => array_merge(self::SCREENS[359], self::SCREENS[467]), // Books, then Stock Verification
            'reports' => self::SCREENS[404],                                      // Library Report
        ];
        foreach ($items as $categoryKey => $menuIds) {
            foreach ($menuIds as $position => $menuId) {
                $exists = DB::table('fees_menu_category_items')->where('module_name', self::SLUG)
                    ->where('category_key', $categoryKey)->where('menu_id', $menuId)->exists();
                if (! $exists) {
                    DB::table('fees_menu_category_items')->insert([
                        'module_name' => self::SLUG,
                        'category_key' => $categoryKey,
                        'menu_id' => $menuId,
                        'sort_order' => $position + 1,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
