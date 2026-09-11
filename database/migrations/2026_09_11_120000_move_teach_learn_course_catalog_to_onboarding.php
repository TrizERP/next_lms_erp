<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULE = 'teach_learn';

    private const ONBOARDING = 'onboarding';

    private const OPERATIONS = 'operations';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasTable('fees_menu_category_items')
            || ! Schema::hasColumn('fees_menu_categories', 'module_name')
            || ! Schema::hasColumn('fees_menu_category_items', 'module_name')) {
            return;
        }

        $parentId = DB::table('tblmenumaster')
            ->where('name', 'Teach/Learn')
            ->where('level', 2)
            ->orderBy('id')
            ->value('id');

        if ($parentId === null) {
            return;
        }

        $courseCatalogId = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->where('name', 'Course Catalog')
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        if ($courseCatalogId === null) {
            return;
        }

        DB::transaction(function () use ($courseCatalogId) {
            $onboardingExists = DB::table('fees_menu_categories')
                ->where('module_name', self::MODULE)
                ->where('category_key', self::ONBOARDING)
                ->where('status', 1)
                ->exists();

            if (! $onboardingExists) {
                return;
            }

            DB::table('fees_menu_category_items')->updateOrInsert(
                [
                    'module_name' => self::MODULE,
                    'category_key' => self::ONBOARDING,
                    'menu_id' => $courseCatalogId,
                ],
                [
                    'sort_order' => 1,
                    'status' => 1,
                    'updated_at' => now(),
                ]
            );

            DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)
                ->where('category_key', self::OPERATIONS)
                ->where('menu_id', $courseCatalogId)
                ->delete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')
            || ! Schema::hasColumn('fees_menu_category_items', 'module_name')) {
            return;
        }

        $parentId = DB::table('tblmenumaster')
            ->where('name', 'Teach/Learn')
            ->where('level', 2)
            ->orderBy('id')
            ->value('id');

        if ($parentId === null) {
            return;
        }

        $courseCatalogId = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->where('name', 'Course Catalog')
            ->orderBy('id')
            ->value('id');

        if ($courseCatalogId === null) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)
            ->where('category_key', self::ONBOARDING)
            ->where('menu_id', $courseCatalogId)
            ->delete();

        DB::table('fees_menu_category_items')->updateOrInsert(
            [
                'module_name' => self::MODULE,
                'category_key' => self::OPERATIONS,
                'menu_id' => $courseCatalogId,
            ],
            [
                'sort_order' => 1,
                'status' => 1,
                'updated_at' => now(),
            ]
        );
    }
};
