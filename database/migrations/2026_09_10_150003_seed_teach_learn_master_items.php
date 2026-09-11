<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds every active LMS + PAL master screen to Teach/Learn Master Setup. */
return new class extends Migration
{
    private const MODULE = 'teach_learn';

    private const CATEGORY = 'master-setup';

    /** ordered [level-2 parent, master menu name] pairs */
    private const ITEMS = [
        ['Teach/Learn', 'LMS Global Mapping'],
        ['Interactions', 'Leader Board Master'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('fees_menu_category_items')
            || ! Schema::hasColumn('fees_menu_category_items', 'module_name')) {
            return;
        }

        foreach (self::ITEMS as $sortOrder => [$parentName, $menuName]) {
            $parentId = DB::table('tblmenumaster')
                ->where('name', $parentName)
                ->where('level', 2)
                ->where('parent_menu_id', 230)
                ->orderBy('id')
                ->value('id');

            if ($parentId === null) {
                continue;
            }

            $menuId = DB::table('tblmenumaster')
                ->where('parent_menu_id', $parentId)
                ->where('name', $menuName)
                ->where('level', 3)
                ->where('menu_type', 'MASTER')
                ->where('status', 1)
                ->orderBy('id')
                ->value('id');

            if ($menuId === null) {
                continue;
            }

            DB::table('fees_menu_category_items')->updateOrInsert(
                [
                    'module_name' => self::MODULE,
                    'category_key' => self::CATEGORY,
                    'menu_id' => $menuId,
                ],
                [
                    'sort_order' => $sortOrder + 1,
                    'status' => 1,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)
            ->where('category_key', self::CATEGORY)
            ->whereIn('menu_id', [275, 311])
            ->delete();
    }
};