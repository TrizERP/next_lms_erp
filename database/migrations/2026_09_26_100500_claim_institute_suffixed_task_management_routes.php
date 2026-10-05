<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management's AI Stack route carries the level-2 MENU ID, and the module did not
 * claim it.
 *
 * WHAT WAS WRONG
 *
 * Nearly every module's AI Stack lives at `/modules/<slug>/ai-stack`, and the slug is the
 * module name. Task Management is the exception: its level-2 menu exists per institute
 * with a different id each time, so `fees_menu_categories` carries a slug per institute —
 * `task-management-253` and `task-management-551` on this estate — and the routes are
 *
 *     /modules/task-management-253/ai-stack
 *     /modules/task-management-551/ai-stack
 *
 * The module claimed `/modules/task-management/**`, which does not match either of them:
 * `RouteMatcher` compares segments literally and `task-management-253` is a different
 * segment from `task-management`. So a question asked on Task Management's own AI Stack
 * resolved to the general module and was answered with no tools at all — the module looked
 * registered everywhere except in the one place it mattered.
 *
 * `check_ai_stack_coverage.php` found this by resolving each menu row's ACTUAL route
 * instead of assuming the conventional one.
 *
 * WHY THE SLUGS ARE LISTED RATHER THAN PATTERNED
 *
 * `RouteMatcher`'s `*` matches a whole segment, so `/modules/task-management-*` is not
 * expressible — and a pattern loose enough to catch the suffix would also catch every
 * other module's AI Stack, which is the opposite of what this system is for. So the two
 * that exist are named, and the coverage check fails loudly if a third appears. That is
 * the right trade: a new institute's menu needs one line here and one in
 * `module-static-screens.tsx`, and until then it is reported rather than silently broken.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_26_100500_claim_institute_suffixed_task_management_routes.php
 */
return new class extends Migration
{
    private const MODULE_KEY = 'task_management';

    public function up(): void
    {
        $this->mergePatterns(static fn (array $existing) => array_values(array_unique(array_merge($existing, self::discover()))));
    }

    public function down(): void
    {
        $this->mergePatterns(static fn (array $existing) => array_values(array_diff($existing, self::discover())));
    }

    /**
     * The suffixed slugs this estate actually has, read from the menu rather than assumed.
     *
     * Derived rather than hard-coded so that running this on an estate with different
     * level-2 menu ids claims the right routes there too.
     *
     * @return array<int, string>
     */
    private static function discover(): array
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return [];
        }

        $patterns = [];

        $slugs = DB::table('fees_menu_categories')
            ->where('module_name', 'like', 'task-management-%')
            ->distinct()
            ->pluck('module_name');

        foreach ($slugs as $slug) {
            // Only a numeric suffix, so this cannot be widened by a slug somebody names
            // `task-management-archive`.
            if (preg_match('/^task-management-\d+$/', (string) $slug) !== 1) {
                continue;
            }

            $patterns[] = '/modules/'.$slug;
            $patterns[] = '/modules/'.$slug.'/**';
        }

        return $patterns;
    }

    /**
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function mergePatterns(callable $transform): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        foreach (DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->get(['id', 'route_patterns']) as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                continue;
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'route_patterns' => json_encode($transform(array_values(array_filter($patterns, 'is_string')))),
                'updated_at' => now(),
            ]);
        }
    }
};
