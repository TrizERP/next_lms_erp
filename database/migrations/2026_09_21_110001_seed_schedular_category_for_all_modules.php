<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every module bar the Schedular category Fees has, and points each one
 * at the platform-services module whose scheduled tasks it configures.
 *
 * The Workflow rollout's twin, one step later and for the same reason: the
 * scheduler console exists centrally, Fees was the only bar that offered a way
 * into it from inside the module, and every other module's administrators had
 * to go to Platform services and find their module in the rail.
 *
 * WHAT IS SEEDED. One `schedular` category per bar — the Fees row's spelling,
 * kept so the two match and a lookup by key finds both — at sort_order 12, so
 * it lands after Workflow exactly as it does in Fees. Routed at
 * /modules/<slug>/schedular, the dynamic category page, whose URL segment is
 * the category key. The Fees row predates this and is left untouched, keeping
 * its own route (/fees/scheduler), label and description.
 *
 * WHERE THE MODULE KEY COMES FROM. Not from a second copy of the bar-to-module
 * mapping: it is read off the bar's own Workflow row, which
 * 2026_09_21_100001 filled. Workflow and Scheduler read one registry and one
 * set of module keys, so a bar that pins Workflow to `front_desk` pins
 * Scheduler there too, and correcting one corrects both. A bar with no key
 * there gets none here — every declared module has scheduled tasks, so a blank
 * means the bar has no counterpart in the registry at all, and the category
 * page says so rather than showing another module's jobs.
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'schedular';

    /** After Workflow (11), which is the order Fees puts them in. */
    private const SORT_ORDER = 12;

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

            if (! $exists) {
                DB::table('fees_menu_categories')->insert([
                    'module_name' => $bar->module_name,
                    'level2_menu_id' => $bar->level2_menu_id,
                    'category_key' => self::CATEGORY_KEY,
                    'label' => 'Schedular',
                    'description' => sprintf('Scheduled tasks for %s.', $bar->label),
                    'route' => '/modules/'.$bar->module_name.'/'.self::CATEGORY_KEY,
                    'sort_order' => self::SORT_ORDER,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ((string) $bar->platform_module_key === '') {
                continue;
            }

            DB::table('fees_menu_categories')
                ->where('module_name', $bar->module_name)
                ->where('category_key', self::CATEGORY_KEY)
                ->whereNull('platform_module_key')
                ->update(['platform_module_key' => $bar->platform_module_key, 'updated_at' => $now]);
        }
    }

    /**
     * Removes the categories this migration created, leaving the Fees row that
     * predates it and the Workflow rows the key was read from.
     */
    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('category_key', self::CATEGORY_KEY)
            ->where('module_name', '<>', 'fees')
            ->delete();

        DB::table('fees_menu_categories')
            ->where('category_key', self::CATEGORY_KEY)
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'platform_module_key')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')
            && Schema::hasTable('tblmenumaster');
    }

    /**
     * Every module bar, with the module key its Workflow row carries.
     *
     * Left join, so a bar whose Workflow row is missing or unmapped still gets
     * its Schedular category — without a key, which is the honest answer rather
     * than a skipped menu.
     *
     * @return Collection<int,object>
     */
    private function bars(): Collection
    {
        return DB::table('fees_menu_categories as c')
            ->leftJoin('tblmenumaster as m', 'm.id', '=', 'c.level2_menu_id')
            ->leftJoin('fees_menu_categories as w', function ($join) {
                $join->on('w.module_name', '=', 'c.module_name')
                    ->where('w.category_key', '=', 'workflow');
            })
            ->where('c.status', 1)
            ->groupBy('c.module_name', 'c.level2_menu_id', 'm.name', 'w.platform_module_key')
            ->orderBy('c.module_name')
            ->get([
                'c.module_name',
                'c.level2_menu_id',
                DB::raw('COALESCE(m.name, c.module_name) as label'),
                DB::raw("COALESCE(w.platform_module_key, '') as platform_module_key"),
            ]);
    }
};
