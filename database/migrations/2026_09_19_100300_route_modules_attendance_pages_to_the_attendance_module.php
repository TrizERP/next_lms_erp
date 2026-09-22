<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teaches the AI workspace that `/modules/attendance/**` is the Attendance module.
 *
 * WHY THIS IS NEEDED
 *
 * `AiContextService` resolves "which module is this page" from `ai_modules.route_patterns`
 * — deliberately data, so adding a module to the workspace is a row rather than a code
 * change. The Attendance row carried `/attendance` and `/attendance/**` only, which was
 * right when every attendance screen lived under that prefix.
 *
 * The category pages do not. `2026_09_17_100001_seed_all_module_menu_categories.php`
 * routes 62 modules through `/modules/<module>/<category>`, and the Attendance AI Stack is
 * one of them. On that route the resolver matched no module at all, which is not a
 * cosmetic failure: `ModuleToolData` grounds a generation from the resolved module's read
 * tools, so a summary asked for from the AI Stack was refused with "there is nothing to
 * summarise" while the register sat there full of rows, and a report built from the same
 * page came back "this page is not a module a report can be built from".
 *
 * WHAT IT DOES NOT DO
 *
 * It adds two patterns to one row. It removes none, touches no other module — Fees keeps
 * `/fees` and `/fees/**` exactly as they are — and grants nothing: `route_patterns` says
 * what a page is about, never who may read it, and every tool the module reaches still
 * authorises against the caller's own token.
 *
 * The same one-row change would fix the other 61 modules on that route. It is not made
 * here because each is a decision about that module, and a migration that quietly rewrote
 * sixty rows to fix one screen would be the wrong shape of change.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_19_100300_route_modules_attendance_pages_to_the_attendance_module.php
 */
return new class extends Migration
{
    private const MODULE_KEY = 'attendance';

    private const PATTERNS = ['/modules/attendance', '/modules/attendance/**'];

    public function up(): void
    {
        $this->apply(fn (array $patterns) => array_values(array_unique(array_merge($patterns, self::PATTERNS))));
    }

    public function down(): void
    {
        $this->apply(fn (array $patterns) => array_values(array_diff($patterns, self::PATTERNS)));
    }

    /**
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function apply(callable $transform): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        // Every Attendance row, platform and per-institute: a school that has customised
        // its own row should not be left on the old patterns.
        $rows = DB::table('ai_modules')->where('module_key', self::MODULE_KEY)->get(['id', 'route_patterns']);

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                // A row whose patterns are unreadable is not one to guess at — replacing
                // it would drop whatever it was actually matching on.
                continue;
            }

            $next = $transform(array_values(array_filter($patterns, 'is_string')));

            DB::table('ai_modules')->where('id', $row->id)->update([
                'route_patterns' => json_encode($next),
                'updated_at' => now(),
            ]);
        }
    }
};
