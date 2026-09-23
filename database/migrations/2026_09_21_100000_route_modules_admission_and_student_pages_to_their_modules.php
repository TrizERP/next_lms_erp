<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teaches the AI workspace that `/modules/admission/**` is Admissions and
 * `/modules/student/**` is Students.
 *
 * WHY THIS IS NEEDED
 *
 * `AiContextService` resolves "which module is this page" from `ai_modules.route_patterns`
 * — deliberately data, so adding a module to the workspace is a row rather than a code
 * change. The Admissions row carried `/admissions` and `/admission-Enquiry` only; the
 * Students row carried `/students` and `/student`. Neither covers the category route.
 *
 * `2026_09_17_100001_seed_all_module_menu_categories.php` routes 62 modules through
 * `/modules/<module>/<category>`, and the two AI Stacks this migration exists for are on
 * it — the Admission module's slug is `admission` (level-2 menu 173) and the Student
 * module's is `student` (level-2 menu 259). On those routes the resolver matched no module
 * at all, which is not cosmetic: `ModuleToolData` grounds a generation from the resolved
 * module's read tools, so a summary asked for from the AI Stack would be refused with
 * "there is nothing to summarise" while the enquiry list sat there full of rows, and a
 * report built from the same page would come back "this page is not a module a report can
 * be built from".
 *
 * This is the same one-row change 2026_09_19_100300 made for Attendance, applied to the
 * two modules that now have an AI Stack of their own.
 *
 * WHY THE SLUG AND THE MODULE KEY DIFFER, AND WHY THAT IS FINE
 *
 * The menu slug is derived from the level-2 menu's name ("Admission" → `admission`,
 * "Student" → `student`); the `ai_modules` key is the one the workspace has always used
 * (`admissions`, `students`). A route pattern is how a URL is recognised, not what the
 * module is called, so the pattern carries the slug and the row keeps its key. Nothing
 * else in the system has to agree on a single spelling.
 *
 * WHY `/modules/student/**` CANNOT CAPTURE ANOTHER MODULE
 *
 * `RouteMatcher` compiles a pattern segment by segment, so `student` matches the segment
 * `student` exactly. `/modules/student-report/...` and `/modules/student-i-card/...` are
 * different segments and are not matched. `**` only consumes what follows.
 *
 * WHAT IT DOES NOT DO
 *
 * It adds patterns to two rows. It removes none, touches no other module — Fees keeps
 * `/fees` and `/fees/**`, Attendance keeps what 2026_09_19_100300 gave it — and grants
 * nothing: `route_patterns` says what a page is about, never who may read it, and every
 * tool the module reaches still authorises against the caller's own token.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_21_100000_route_modules_admission_and_student_pages_to_their_modules.php
 */
return new class extends Migration
{
    /**
     * module_key => the patterns to add.
     *
     * The Admissions row already carries `/admissions/**`, which covers the Next app's own
     * `/admissions/ai-stack` page; only the category route is missing. The Students row
     * already carries `/student/**`, which covers `/student/ai-stack` for the same reason.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        'admissions' => ['/modules/admission', '/modules/admission/**'],
        'students' => ['/modules/student', '/modules/student/**'],
    ];

    public function up(): void
    {
        foreach (self::PATTERNS as $moduleKey => $patterns) {
            $this->apply(
                $moduleKey,
                fn (array $existing) => array_values(array_unique(array_merge($existing, $patterns)))
            );
        }
    }

    public function down(): void
    {
        foreach (self::PATTERNS as $moduleKey => $patterns) {
            $this->apply($moduleKey, fn (array $existing) => array_values(array_diff($existing, $patterns)));
        }
    }

    /**
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function apply(string $moduleKey, callable $transform): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        // Every row for the key, platform and per-institute: a school that has customised
        // its own row should not be left on the old patterns.
        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'route_patterns']);

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                // A row whose patterns are unreadable is not one to guess at — replacing it
                // would drop whatever it was actually matching on.
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
