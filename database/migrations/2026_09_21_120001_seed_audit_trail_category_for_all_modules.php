<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every module bar the Audit Trail category Fees has, and works out which
 * access-log rows each one should show.
 *
 * The third of the Fees-only categories to roll out, after Workflow and
 * Schedular. The screen already existed — Fees → Audit trail reads the same
 * access log as the User Log report, narrowed to one module — but only Fees
 * offered it, and it was narrowed by a literal written into the page.
 *
 * WHAT IS SEEDED. One `audit-trail` category per bar at sort_order 13, after
 * Schedular, which is the order Fees puts the three in. Routed at
 * /modules/<slug>/audit-trail, the dynamic category page. The Fees row predates
 * this and keeps its own route (/fees/audit-trail), label and description; only
 * its prefixes are filled in.
 *
 * HOW THE PREFIXES ARE DERIVED. `access_log_route.module` is the first path
 * segment of the URL that was opened (LogRouteMiddleware), so a bar's rows are
 * the ones whose prefix matches one of the bar's own screens. Each level-3 menu
 * under the bar contributes one:
 *
 *   '/fees/collect'                  -> 'fees'      a path, read directly
 *   'result_activity_marks_V1.index' -> 'result'    a route name, resolved
 *
 * The second form matters: 17 bars carry nothing but route names, and reading
 * only paths would have left them empty. What cannot be resolved is left out
 * rather than guessed at from the menu's label.
 *
 * Four bars end with no prefixes at all — Career Awareness, Career Explorer,
 * LMS and Platform services — because their screens live only in the Next app
 * and never pass through the middleware that writes this log. NULL says so, and
 * the category page says so in turn. An empty table would read as "nobody used
 * this module", which is a different and untrue statement.
 *
 * Rows that already carry prefixes are left alone, so a correction by hand
 * survives a re-run.
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'audit-trail';

    /** After Schedular (12), which is the order Fees puts the three in. */
    private const SORT_ORDER = 13;

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $routeUris = $this->routeUris();
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
                    'label' => 'Audit Trail',
                    'description' => sprintf('Who opened which %s screen, and when.', $bar->label),
                    'route' => '/modules/'.$bar->module_name.'/'.self::CATEGORY_KEY,
                    'sort_order' => self::SORT_ORDER,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $prefixes = $this->prefixes((int) $bar->level2_menu_id, $routeUris);

            if ($prefixes === '') {
                continue;
            }

            DB::table('fees_menu_categories')
                ->where('module_name', $bar->module_name)
                ->where('category_key', self::CATEGORY_KEY)
                ->whereNull('audit_module_keys')
                ->update(['audit_module_keys' => $prefixes, 'updated_at' => $now]);
        }
    }

    /**
     * Removes the categories this migration created, leaving the Fees row that
     * predates it.
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
            ->update(['audit_module_keys' => null, 'updated_at' => now()]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'audit_module_keys')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')
            && Schema::hasTable('tblmenumaster');
    }

    /**
     * The log prefixes one bar's screens write, comma-separated and sorted so
     * the value never depends on menu order.
     *
     * @param  array<string,string>  $routeUris
     */
    private function prefixes(int $level2MenuId, array $routeUris): string
    {
        if ($level2MenuId <= 0) {
            return '';
        }

        $links = DB::table('tblmenumaster')
            ->where('parent_menu_id', $level2MenuId)
            ->where('level', 3)
            ->where('status', 1)
            ->pluck('link');

        $prefixes = [];

        foreach ($links as $link) {
            $link = trim((string) $link);

            if ($link === '') {
                continue;
            }

            // A path is read as it stands; anything else is a route name, and
            // only the router can say where it points.
            $path = str_contains($link, '/') ? $link : ($routeUris[$link] ?? '');

            if ($path === '') {
                continue;
            }

            $prefix = strtolower(explode('?', explode('/', ltrim($path, '/'))[0])[0]);

            // A dot means an unresolved route name; a brace means the first
            // segment is a parameter, which is no prefix at all.
            if ($prefix === '' || str_contains($prefix, '.') || str_starts_with($prefix, '{')) {
                continue;
            }

            $prefixes[$prefix] = true;
        }

        $prefixes = array_keys($prefixes);
        sort($prefixes);

        return implode(',', $prefixes);
    }

    /** @return array<string,string> route name => uri */
    private function routeUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (is_string($name) && $name !== '') {
                $uris[$name] = $route->uri();
            }
        }

        return $uris;
    }

    /**
     * Every module bar, named the way the existing categories name it — the
     * same reading as the Workflow and Schedular rollouts.
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
