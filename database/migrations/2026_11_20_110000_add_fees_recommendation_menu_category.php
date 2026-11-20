<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "Recommendation" category next to Fees' existing "Knowledge Graph"
 * tab (category_key='knowledge-graph', sort_order 14, seeded for every
 * module by 2026_11_02_320000). Unlike that migration this one is Fees-only:
 * the Recommendation panel's backing data (FeesGraphRecommendationEngine)
 * only exists for Fees today, so no other module gets a row here.
 *
 * Reuses the same `level2_menu_id` as Fees' own "knowledge-graph" row so it
 * attaches to the same module bar, rather than hardcoding the id (fragile
 * across tenants — see 2026_09_17_100000's own reasoning for why this
 * column exists at all).
 */
return new class extends Migration
{
    private const MODULE_NAME = 'fees';

    private const CATEGORY_KEY = 'recommendation';

    /** Right after Fees' own "knowledge-graph" row (14). */
    private const SORT_ORDER = 15;

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $exists = DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->exists();

        if ($exists) {
            return;
        }

        $level2MenuId = DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', 'knowledge-graph')
            ->value('level2_menu_id');

        $now = now();

        DB::table('fees_menu_categories')->insert([
            'module_name' => self::MODULE_NAME,
            'level2_menu_id' => $level2MenuId,
            'category_key' => self::CATEGORY_KEY,
            'label' => 'Recommendation',
            'description' => 'Graph-based intelligence and recommendations for Fees, read directly from Neo4j.',
            'route' => '/fees/recommendation',
            'sort_order' => self::SORT_ORDER,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->delete();
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'module_name')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id');
    }
};
