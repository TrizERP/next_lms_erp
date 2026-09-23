<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Users Mobile Apps, Student I-Card, Certificate, Communication, Time Table and
 * Student Medical as AI modules.
 *
 * WHAT A MODULE NEEDS BEFORE IT CAN HAVE AN AI STACK
 *
 * A row in `ai_modules`. Without one, `TemplateModuleCatalog::exists()` answers no and the
 * Prompts and Templates tabs 404 with "that module is not one this school has";
 * `AiPolicyController` resolves no scope id and the Policies tab refuses to save; and
 * `fetchModuleUsage` reports the module unregistered. That is the failure the five modules
 * before these shipped with, and this migration is what prevents it here.
 *
 * Plus the route patterns that let `AiContextService` resolve a question asked on one of
 * the module's pages, and the `generative` capability the companion migration's prompts
 * and layouts are for.
 *
 * COMMUNICATION IS NOT CREATED — IT IS EXTENDED
 *
 * `easy_com` has had a row since the workspace was seeded, carrying `/easy_com` and
 * `/easy_com/**`, which is exactly where the Communication menu's five screens live.
 * Creating a second `communication` row would split one module across two keys: two policy
 * scopes, two template lists, two ledgers, and a question answered from whichever the page
 * resolved to. So `easy_com` gains the category route and the capability, and keeps
 * everything it had.
 *
 * WHY THE ROUTE PATTERNS ARE NARROW
 *
 * Several of these modules' screens live under another module's tree, and claiming the
 * tree would take that module's pages with them:
 *
 *   · Student I-Card and Student Medical sit under `/student/…`, which the Students module
 *     claims as `/student/**`. Each page is named individually, and `RouteMatcher` scores
 *     a literal segment (10) above a wildcard (1) — so `/student/student_icard` wins for
 *     the I-card page and every other student page is untouched.
 *   · Time Table's screens sit under `/front_desk/…`, which belongs to the front_desk
 *     module. Same treatment, same reason.
 *   · Users Mobile Apps' level-3 menus point at Calendar, Photo Gallery, Leave and Exam
 *     Schedule — pages owned by front_desk and students. NONE of them is claimed here. The
 *     module's own surface is its AI Stack and its home-screen configuration, and taking
 *     four other modules' pages to look better populated would be the leak this whole
 *     design exists to prevent.
 *
 * NOTHING BELONGING TO ANY EXISTING MODULE IS MODIFIED. Every write is keyed on one of the
 * six module keys, and the `easy_com` row is merged into — patterns added, never replaced.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_23_100000_register_six_module_ai_stacks.php
 */
return new class extends Migration
{
    /**
     * The five modules with no `ai_modules` row yet. Communication is not among them.
     *
     * `match_priority` 80 is what every module seeded by 2026_08_21_000001 carries.
     *
     * @var array<string, array{label:string, description:string, icon:string, sort_order:int}>
     */
    private const NEW_MODULES = [
        'mobile_apps' => [
            'label' => 'Users Mobile Apps',
            'description' => 'The parent, student and teacher apps, and the home screens configured for them.',
            'icon' => 'smartphone',
            'sort_order' => 240,
        ],
        'student_icard' => [
            'label' => 'Student I-Card',
            'description' => 'The students a card can be printed for, and the fields a card carries.',
            'icon' => 'id-card',
            'sort_order' => 250,
        ],
        'certificate' => [
            'label' => 'Certificate',
            'description' => 'Certificates issued, and the layouts they are issued from.',
            'icon' => 'award',
            'sort_order' => 260,
        ],
        'timetable' => [
            'label' => 'Time Table',
            'description' => 'The published class timetable, its periods, subjects and teachers.',
            'icon' => 'calendar-clock',
            'sort_order' => 270,
        ],
        'student_medical' => [
            'label' => 'Student Medical',
            'description' => 'Infirmary visits, vaccinations, growth measurements and health notes.',
            'icon' => 'stethoscope',
            'sort_order' => 280,
        ],
    ];

    /**
     * The patterns each module's row must carry, added to whatever it already has.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        // No `/front_desk/**`, no `/students/**`: this module's level-3 menus point at
        // pages four other modules own. See the note at the top.
        'mobile_apps' => ['/modules/mobile-apps', '/modules/mobile-apps/**', '/mobile-apps', '/mobile-apps/**'],

        'student_icard' => [
            '/modules/student-i-card',
            '/modules/student-i-card/**',
            // Named one page at a time, each more specific than the Students module's
            // `/student/**`. `my_icard` is the student's own view of the same card.
            '/student/student_icard',
            '/student/student_icard/**',
            '/student/my_icard',
        ],

        'certificate' => [
            '/modules/certificate',
            '/modules/certificate/**',
            '/student/student_certificate',
            '/student/student_certificate/**',
            '/student/student_certificate_report',
        ],

        // Already carries /easy_com and /easy_com/**.
        'easy_com' => ['/modules/communication', '/modules/communication/**'],

        'timetable' => [
            '/modules/timetable',
            '/modules/timetable/**',
            // More specific than the front_desk module's `/front_desk/**`.
            '/front_desk/create-timetable',
            '/front_desk/classwisetimetable',
            '/front_desk/facultywisetimetable',
        ],

        'student_medical' => [
            '/modules/student-medical',
            '/modules/student-medical/**',
            '/student/student_infirmary',
            '/student/student_vaccination',
            '/student/student_hw',
            '/student/student_health',
            '/student/report/student_health_report',
        ],
    ];

    /**
     * The conversational chips the assistant offers on each module's pages.
     *
     * Every one is a question the module's own bound read tools can answer, and none names
     * a record: they are questions, not data. `easy_com` already has its own from
     * 2026_08_21_000001 and is left alone.
     *
     * The Student Medical chips are deliberately operational — open cases, what is
     * recorded — and none of them asks the assistant to interpret a child's condition,
     * because no tool behind them would.
     *
     * @var array<string, array<int, string>>
     */
    private const SUGGESTIONS = [
        'mobile_apps' => [
            'Which tiles are switched off on the parent app home screen?',
            'What sections does the teacher app have that the parent app does not?',
            'How is the mobile app home screen configured for each user profile?',
        ],
        'student_icard' => [
            'Which students are missing a photo for their identity card?',
            'How many cards are ready to print for this class?',
            'Which students have a bus and stop recorded for their card?',
        ],
        'certificate' => [
            'Which certificates have been issued this year?',
            'What certificate types can this school issue?',
            'How many transfer certificates were issued this term?',
        ],
        'timetable' => [
            'Is any teacher booked into two classes in the same period?',
            'What is the timetable for this class this week?',
            'Which periods is this teacher scheduled for?',
        ],
        'student_medical' => [
            'Which infirmary cases are still open?',
            'What vaccinations have been recorded this year?',
            'How many students were seen in the infirmary this month?',
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
            // six has a manifest, and `ModuleRegistry` would strip the flag anyway rather
            // than offer a stage it cannot reach.
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

        // Only Communication needs its capability restored: the other five rows are
        // deleted below and their flags go with them.
        $this->setCapabilities('easy_com', ['generative' => false]);

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->whereIn('module_key', array_keys(self::SUGGESTIONS))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Only the five rows this migration created. `easy_com` was seeded by
        // 2026_08_21_000001 and is left exactly as it was.
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
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function mergePatterns(string $moduleKey, callable $transform): void
    {
        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'route_patterns']);

        foreach ($rows as $row) {
            $patterns = json_decode((string) $row->route_patterns, true);

            if (! is_array($patterns)) {
                // A row whose patterns are unreadable is not one to guess at — replacing it
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
