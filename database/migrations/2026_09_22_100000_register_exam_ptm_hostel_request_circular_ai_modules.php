<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Exam, PTM, Hostel, Student Request and Circular as AI modules.
 *
 * WHAT A MODULE NEEDS BEFORE IT CAN HAVE AN AI STACK
 *
 * Three things, and this migration supplies the ones that live in the database:
 *
 *   1. A row in `ai_modules`. Without it `fetchModuleUsage`, `fetchModuleGuardrails` and
 *      the policy scope all report the module as unregistered, the Templates selector
 *      does not list it, and the AI Stack tabs correctly refuse to attribute anything to
 *      it. `exam` and `hostel` already have rows (seeded 2026_08_21_000001); `ptm`,
 *      `student_request` and `circular` do not and are created here.
 *   2. Route patterns that match the pages the module is actually reached at, so
 *      `AiContextService` resolves a question asked from one of them to this module
 *      rather than to the general one.
 *   3. The `generative` capability, so a report can be built and a prompt run. The
 *      companion migration 2026_09_22_100100 publishes the prompts and layouts that
 *      capability is for; this one turns it on, matching what
 *      2026_09_10_000002 did for Fees and 2026_09_19_100200 for Attendance.
 *
 * The third thing - the tool bindings - lives in `config/ai.php`, deliberately, because
 * it names tools that only exist in code. See the module blocks there.
 *
 * WHY THE ROUTE PATTERNS ARE WHAT THEY ARE
 *
 * Each module's AI Stack is reached two ways, and both have to resolve:
 *
 *   /modules/<menu slug>/ai-stack   the seeded category route every module shares
 *   /<the module's own pages>       the direct route the module's screens live under
 *
 * The menu slug and the `ai_modules` key are not always the same word, and they do not
 * have to be: a route pattern is how a URL is recognised, not what the module is called.
 * The slugs below are the `fees_menu_categories.module_name` values this estate actually
 * carries - exam, ptm, hostel, student-request and circular.
 *
 * `/students/requests` IS MORE SPECIFIC THAN `/students/**`, AND THAT IS HOW IT WINS
 *
 * The Students module already claims `/students/**`. `RouteMatcher::best()` scores literal
 * segments above wildcards, so `/students/requests` (20) beats `/students/**` (11) for the
 * Student Request pages, and every other student page is untouched. Nothing is removed
 * from the Students row.
 *
 * NOTHING BELONGING TO FEES, ATTENDANCE, ADMISSIONS OR STUDENTS IS MODIFIED. Every write
 * is keyed on one of the five module keys below. The two rows that already exist are
 * merged into - patterns are added, never replaced, and a capability already on stays on.
 *
 * Run it on its own - `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_22_100000_register_exam_ptm_hostel_request_circular_ai_modules.php
 */
return new class extends Migration
{
    /**
     * The three modules with no `ai_modules` row yet.
     *
     * `match_priority` 80 is what every module seeded by 2026_08_21_000001 carries; the
     * entity-bound `student` module sits above it, which is what lets a single student's
     * page beat a module's list page for the same URL. Nothing here is entity-bound.
     *
     * @var array<string, array{label:string, description:string, icon:string, sort_order:int}>
     */
    private const NEW_MODULES = [
        'ptm' => [
            'label' => 'PTM',
            'description' => 'Parent-teacher meeting slots, bookings and attendance.',
            'icon' => 'calendar-check',
            'sort_order' => 210,
        ],
        'student_request' => [
            'label' => 'Student requests',
            'description' => 'Change requests raised against a student record, and their approvals.',
            'icon' => 'file-question',
            'sort_order' => 220,
        ],
        'circular' => [
            'label' => 'Circulars',
            'description' => 'Circulars published to classes, with their types and attachments.',
            'icon' => 'megaphone',
            'sort_order' => 230,
        ],
    ];

    /**
     * The patterns each module's row must carry, added to whatever it already has.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        // Already carries /exam, /exam/**, /result and /result/**.
        'exam' => ['/modules/exam', '/modules/exam/**'],

        'ptm' => [
            '/modules/ptm',
            '/modules/ptm/**',
            // The PTM screens live under Admin Services in the Next app, which is where
            // the level-3 menus point. Named one by one rather than claiming
            // `/admin-services/**`, which belongs to the admin-services module and covers
            // visitors, complaints and petty cash as well.
            '/admin-services/ptm-attended-status',
            '/admin-services/ptm-report',
            '/admin-services/ptm-time-slot-master',
            '/admin-services/ptm-ai-stack',
        ],

        // Already carries /hostel and /hostel/**.
        'hostel' => ['/modules/hostel', '/modules/hostel/**'],

        'student_request' => [
            '/modules/student-request',
            '/modules/student-request/**',
            // More specific than the Students module's `/students/**`, so it wins for these
            // pages and for nothing else. See the note at the top.
            '/students/requests',
            '/students/requests/**',
            '/student/report/student_request_report',
        ],

        'circular' => [
            '/modules/circular',
            '/modules/circular/**',
            '/front_desk/circular',
            '/front_desk/circular/**',
        ],
    ];

    /**
     * The conversational chips the assistant offers on each module's pages.
     *
     * Every one is a question the module's own bound read tools can actually answer, and
     * none of them names a record: they are questions, not data. `exam` and `hostel`
     * already have their own from 2026_08_20_000012 and 2026_08_21_000001 and are left
     * alone.
     *
     * @var array<string, array<int, string>>
     */
    private const SUGGESTIONS = [
        'ptm' => [
            'Which parent-teacher meetings are scheduled?',
            'How many families booked the last PTM, and how many attended?',
            'Which PTM bookings still have no attendance recorded?',
        ],
        'student_request' => [
            'Which student requests are still pending?',
            'What kinds of student request can families raise here?',
            'Which requests were approved or rejected this year?',
        ],
        'circular' => [
            'Which circulars have been published this year?',
            'Which classes was the most recent circular sent to?',
            'How many circulars went out under each type?',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $this->createMissingModules();

        foreach (self::PATTERNS as $moduleKey => $patterns) {
            $this->mergePatterns(
                $moduleKey,
                static fn (array $existing) => array_values(array_unique(array_merge($existing, $patterns)))
            );

            // Generative only. `agent`, `workflow` and `ontology` stay off: none of these
            // five modules has a manifest, and `ModuleRegistry` would strip the flag anyway
            // rather than offer a stage it cannot reach. Turning one on here would be a
            // promise the code cannot keep.
            $this->setCapabilities($moduleKey, ['conversational' => true, 'generative' => true]);
        }

        $this->seedSuggestions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        foreach (self::PATTERNS as $moduleKey => $patterns) {
            $this->mergePatterns(
                $moduleKey,
                static fn (array $existing) => array_values(array_diff($existing, $patterns))
            );
        }

        // `generative` is turned back off only for the two modules that had it off before
        // this migration ran. The three new rows go away entirely below, so their
        // capabilities go with them.
        foreach (['exam', 'hostel'] as $moduleKey) {
            $this->setCapabilities($moduleKey, ['generative' => false]);
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->whereIn('module_key', array_keys(self::SUGGESTIONS))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Only the three rows this migration created. `exam` and `hostel` were seeded by
        // 2026_08_21_000001 and 2026_08_20_000012 and are left exactly as they are.
        DB::table('ai_modules')
            ->whereIn('module_key', array_keys(self::NEW_MODULES))
            ->whereNull('sub_institute_id')
            ->delete();
    }

    private function createMissingModules(): void
    {
        foreach (self::NEW_MODULES as $moduleKey => $module) {
            $exists = DB::table('ai_modules')
                ->where('module_key', $moduleKey)
                ->whereNull('sub_institute_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_modules')->insert([
                'module_key' => $moduleKey,
                'label' => $module['label'],
                'domain' => 'k12',
                'description' => $module['description'],
                // Filled in by mergePatterns() immediately after, so the shape of a pattern
                // list is written in exactly one place.
                'route_patterns' => json_encode([]),
                'entity_key' => null,
                'entity_param' => null,
                'capabilities' => json_encode([
                    'conversational' => true,
                    'generative' => false,
                    'agent' => false,
                    'workflow' => false,
                    'ontology' => false,
                ]),
                'allowed_roles' => null,
                'icon' => $module['icon'],
                'sort_order' => $module['sort_order'],
                'match_priority' => 80,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Apply a transform to every row for a module key, platform and per-institute alike.
     *
     * A school that has customised its own row should not be left on the old patterns,
     * which is the same reasoning 2026_09_21_100000 applied for Admissions and Students.
     *
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function mergePatterns(string $moduleKey, callable $transform): void
    {
        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'route_patterns']);

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                // A row whose patterns are unreadable is not one to guess at - replacing it
                // would drop whatever it was actually matching on.
                continue;
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'route_patterns' => json_encode($transform(array_values(array_filter($patterns, 'is_string')))),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Merge capability flags into a module's row, leaving the ones not named alone.
     *
     * @param  array<string, bool>  $changes
     */
    private function setCapabilities(string $moduleKey, array $changes): void
    {
        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'capabilities']);

        foreach ($rows as $row) {
            $capabilities = json_decode((string) $row->capabilities, true);

            if (! is_array($capabilities)) {
                $capabilities = [];
            }

            DB::table('ai_modules')->where('id', $row->id)->update([
                'capabilities' => json_encode(array_merge($capabilities, $changes)),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        foreach (self::SUGGESTIONS as $moduleKey => $prompts) {
            $sort = 10;

            foreach ($prompts as $prompt) {
                $exists = DB::table('ai_suggestions')
                    ->where('module_key', $moduleKey)
                    ->where('prompt', $prompt)
                    ->whereNull('sub_institute_id')
                    ->exists();

                if (! $exists) {
                    DB::table('ai_suggestions')->insert([
                        'module_key' => $moduleKey,
                        'capability' => 'conversational',
                        'label' => $prompt,
                        'action_type' => 'prompt',
                        'action_ref' => null,
                        'prompt' => $prompt,
                        'requires_entity' => 0,
                        'sort_order' => $sort,
                        'status' => 1,
                        'sub_institute_id' => null,
                        'client_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $sort += 10;
            }
        }
    }
};
