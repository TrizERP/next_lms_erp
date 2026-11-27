<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "Recommendation" category next to Teach/Learn's existing
 * "Knowledge Graph" tab (category_key='knowledge-graph', route
 * '/modules/teach_learn/knowledge-graph', sort_order 14, seeded for every
 * module by 2026_11_02_320000_seed_knowledge_graph_category_for_all_modules).
 *
 * Mirrors 2026_11_20_110000_add_fees_recommendation_menu_category exactly:
 * reuses the same level2_menu_id as Teach/Learn's own "knowledge-graph" row
 * (read live, not hardcoded) so it attaches to the same module bar, and
 * routes through the shared `/modules/[moduleKey]/[categoryKey]` page
 * (ModuleCategoryRoute) rather than Teach/Learn's native `/teach-learn/*`
 * routes, since this is cross-module bolt-on infrastructure the same way
 * Knowledge Graph itself is, not a Teach/Learn-native screen.
 *
 * No Knowledge-Graph-off kill switch on this row either, for the same
 * reason as the Fees migration: Predictive/Recommendation/Agentic are
 * SQL-based and must keep working through a Neo4j outage, so only the
 * graph-backed cards inside the tab degrade (see
 * PalRecommendationIntelligenceController::index()).
 */
return new class extends Migration
{
    private const MODULE_NAME = 'teach_learn';

    private const CATEGORY_KEY = 'recommendation';

    /** Right after Teach/Learn's own "knowledge-graph" row (14). */
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
            'description' => 'Graph-based KG recommendation intelligence for Teach/Learn and PAL.',
            'route' => '/modules/teach_learn/recommendation',
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
