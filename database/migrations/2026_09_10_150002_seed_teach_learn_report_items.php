<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the existing LMS Report screens to Teach/Learn's Reports category.
 *
 * The rows reference the existing tblmenumaster records and remain scoped to
 * teach_learn, so Fees rows and permissions are untouched.
 */
return new class extends Migration
{
    private const MODULE = 'teach_learn';

    private const CATEGORY = 'reports';

    /** ordered [menu name, sort order] pairs under the LMS Report parent */
    private const ITEMS = [
        ['Student Analysis Report', 1],
        ['Examwise Progress Report', 2],
        ['Question Wise Report', 3],
        ['PAL Report', 4],
    ];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $parentId = $this->parentId();
        if ($parentId === null) {
            return;
        }

        foreach (self::ITEMS as [$menuName, $sortOrder]) {
            $menuId = DB::table('tblmenumaster')
                ->where('parent_menu_id', $parentId)
                ->where('name', $menuName)
                ->where('status', 1)
                ->orderBy('id')
                ->value('id');

            if ($menuId === null) {
                continue;
            }

            $exists = DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)
                ->where('category_key', self::CATEGORY)
                ->where('menu_id', $menuId)
                ->exists();

            if ($exists) {
                DB::table('fees_menu_category_items')
                    ->where('module_name', self::MODULE)
                    ->where('category_key', self::CATEGORY)
                    ->where('menu_id', $menuId)
                    ->update(['sort_order' => $sortOrder, 'status' => 1, 'updated_at' => now()]);
                continue;
            }

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::MODULE,
                'category_key' => self::CATEGORY,
                'menu_id' => $menuId,
                'sort_order' => $sortOrder,
                'status' => 1,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $parentId = $this->parentId();
        if ($parentId === null) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->whereIn('name', array_column(self::ITEMS, 0))
            ->pluck('id');

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)
            ->where('category_key', self::CATEGORY)
            ->whereIn('menu_id', $menuIds)
            ->delete();
    }

    private function ready(): bool
    {
        return Schema::hasTable('tblmenumaster')
            && Schema::hasTable('fees_menu_category_items')
            && Schema::hasColumn('fees_menu_category_items', 'module_name');
    }

    private function parentId(): ?int
    {
        $id = DB::table('tblmenumaster')
            ->where('name', 'LMS Report')
            ->where('level', 2)
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
};