<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every module bar a "Knowledge Graph" tab, the same way Workflow,
 * Schedular and Audit Trail were rolled out to all of them
 * (2026_09_21_100001 / 110001 / 120001).
 *
 * Unlike Audit Trail this seeds no per-row config — there is nothing here for a
 * module to "already have" the way Fees already had its audit screen. Every bar
 * gets the same category row, pointed at the dynamic
 * /modules/<slug>/knowledge-graph page. Whether that page has anything real to
 * show for a given module depends on ai_ontology_views/ontology_entities
 * coverage, resolved at request time by WorkspaceController::moduleViews() — not
 * on anything seeded here. A module with no coverage yet still gets the tab; the
 * page itself renders an explicit "not available for this module yet" state.
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'knowledge-graph';

    /** After Audit Trail (13), which is the last of the all-module rollouts so far. */
    private const SORT_ORDER = 14;

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $now = now();

        foreach ($this->bars() as $bar) {
            $exists = DB::table('fees_menu_categories')
                ->where('module_name', $bar->module_name)
                ->where('category_key', self::CATEGORY_KEY)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('fees_menu_categories')->insert([
                'module_name' => $bar->module_name,
                'level2_menu_id' => $bar->level2_menu_id,
                'category_key' => self::CATEGORY_KEY,
                'label' => 'Knowledge Graph',
                'description' => sprintf('How %s connects to the rest of the data.', $bar->label),
                'route' => '/modules/'.$bar->module_name.'/'.self::CATEGORY_KEY,
                'sort_order' => self::SORT_ORDER,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Removes every category row this migration created.
     */
    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('category_key', self::CATEGORY_KEY)
            ->delete();
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')
            && Schema::hasTable('tblmenumaster');
    }

    /**
     * Every module bar, named the way the existing categories name it — the same
     * reading as the Workflow, Schedular and Audit Trail rollouts.
     *
     * @return Collection<int,object>
     */
    private function bars(): Collection
    {
        return DB::table('fees_menu_categories as c')
            ->leftJoin('tblmenumaster as m', 'm.id', '=', 'c.level2_menu_id')
            ->where('c.status', 1)
            ->groupBy('c.module_name', 'c.level2_menu_id', 'm.name')
            ->orderBy('c.module_name')
            ->get([
                'c.module_name',
                'c.level2_menu_id',
                DB::raw('COALESCE(m.name, c.module_name) as label'),
            ]);
    }
};
