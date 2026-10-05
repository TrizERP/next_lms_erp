<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the gap between "has an AI Stack tab" and "has an AI Stack".
 *
 * THE DEFECT THIS FIXES
 *
 * 65 level-2 modules offer an AI Stack tab, because `fees_menu_categories` carries an
 * `ai-stack` row for each. Before this migration 28 of them were registered in
 * `ai_modules` and 37 were not — and an unregistered module has nothing to scope by, so
 * every one of its tabs fell through to the same place. `fees-report`, `exam-report`,
 * `payroll`, `leave` and the rest all resolved to `general`, all offered the same data
 * sources, and all showed each other's content. That is the "same data on every module"
 * a reader actually sees.
 *
 * TWO DIFFERENT GAPS, TWO DIFFERENT FIXES
 *
 *   1. REPORT MODULES. `fees-report` is not a module — it is the Fees module's reports.
 *      Its records ARE fee records. So the parent module's row gains the report route and
 *      the question resolves to Fees, with Fees' tools, policy and prompts. No new module,
 *      no new tools, no duplication: this is the same anti-duplication call that made
 *      Communication reuse `easy_com` and Utility reuse `migration-modules`.
 *
 *   2. MODULES WITH THEIR OWN RECORDS AND NO BINDING. Parent Communication (22,729
 *      messages), SQAA (247 criteria, 1,534 document slots), Users (the ERP accounts) and
 *      Library (35,663 titles, 67,487 loans) all hold real data and had no way to read it.
 *      They get it here.
 *
 * WHAT IS DELIBERATELY NOT REGISTERED
 *
 * `leave`, `payroll`, `donation-management`, `engagement`, `interactions`,
 * `skill-management`, `talent-management`, `stock-verification`, `curriculum-planning`,
 * `user-attendance` and the rest hold NO table in this estate — they are menu entries for
 * features that have not been built. Registering them would produce an AI Stack with
 * nothing behind it, which is the failure mode this whole design exists to avoid. They
 * keep the honest empty state, and `AiStackCoverageTest` records which they are so the
 * list is a decision rather than an oversight.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_26_100000_register_remaining_menu_module_ai_stacks.php
 */
return new class extends Migration
{
    /**
     * The one module with no `ai_modules` row at all.
     *
     * @var array<string, array{label:string, description:string, icon:string, sort_order:int}>
     */
    private const NEW_MODULES = [
        'parent_communication' => [
            'label' => 'Parent Communication',
            'description' => 'Messages parents wrote to the school, and whether anybody has replied.',
            'icon' => 'mail-open',
            'sort_order' => 350,
        ],
    ];

    /**
     * Patterns added to each module's existing row.
     *
     * The `-report` entries are the point of this migration: a report page resolves to the
     * module whose records it reports on.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        'parent_communication' => [
            '/modules/parent-communication', '/modules/parent-communication/**',
            // More specific than the front_desk module's `/front_desk/**`.
            '/front_desk/parent_communication', '/front_desk/parent_communication/**',
        ],

        'sqaa' => ['/modules/sqaa', '/modules/sqaa/**', '/modules/sqaa-report', '/modules/sqaa-report/**'],
        'user' => ['/modules/user', '/modules/user/**'],
        'library' => ['/modules/library', '/modules/library/**', '/modules/library-report', '/modules/library-report/**'],

        // Report modules, each pointed at the module whose records it reports on.
        'fees' => ['/modules/fees-report', '/modules/fees-report/**'],
        'admissions' => ['/modules/admission-report', '/modules/admission-report/**'],
        'students' => ['/modules/student-report', '/modules/student-report/**'],
        'exam' => ['/modules/exam-report', '/modules/exam-report/**'],
        'hostel' => ['/modules/hostel-report', '/modules/hostel-report/**'],
        'inventory' => ['/modules/inventory-report', '/modules/inventory-report/**'],
        'inward_outward' => ['/modules/inward-outward-report', '/modules/inward-outward-report/**'],
        'easy_com' => ['/modules/communication-report', '/modules/communication-report/**'],
        'lms' => ['/modules/lms', '/modules/lms/**', '/modules/lms-report', '/modules/lms-report/**'],
        'institute' => ['/modules/institute-report', '/modules/institute-report/**'],
    ];

    /**
     * The conversational chips the assistant offers on each newly-bound module's pages.
     *
     * Only for the modules gaining a stack of their own. The report modules resolve to a
     * parent that already has its own chips, and adding more would double them up.
     *
     * @var array<string, array<int, string>>
     */
    private const SUGGESTIONS = [
        'parent_communication' => [
            'Which messages from parents have not been answered?',
            'How many messages did parents send this month?',
            'What is the oldest message still waiting for a reply?',
        ],
        'sqaa' => [
            'How many document slots have evidence uploaded against them?',
            'Which quality assurance criteria are recorded for this school?',
            'Which evidence rows are marked available but have no file attached?',
        ],
        'user' => [
            'How many active accounts does this institute have?',
            'Which accounts have never recorded a login?',
            'How many accounts does each user profile have?',
        ],
        'library' => [
            'Which library books are overdue?',
            'How many books are currently on loan?',
            'How many copies do we hold of this title?',
        ],
        'lms' => [
            'Which courses are running this year?',
            'What learning activities are recorded for this class?',
        ],
        'institute' => [
            'What is the academic structure of this institute?',
            'Which departments does this institute have?',
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
        }

        // Only the modules gaining a stack of their own. A report module's parent already
        // has its capabilities and must not be touched — Fees in particular carries an
        // agent and a workflow that nothing here may disturb.
        foreach (array_keys(self::SUGGESTIONS) as $moduleKey) {
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

        foreach (array_keys(self::SUGGESTIONS) as $moduleKey) {
            if (! array_key_exists($moduleKey, self::NEW_MODULES)) {
                $this->setCapabilities($moduleKey, ['generative' => false]);
            }
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->whereIn('module_key', array_keys(self::SUGGESTIONS))
                ->whereNull('sub_institute_id')
                ->delete();
        }

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
     * @param  callable(array<int, string>): array<int, string>  $transform
     */
    private function mergePatterns(string $moduleKey, callable $transform): void
    {
        $rows = DB::table('ai_modules')->where('module_key', $moduleKey)->get(['id', 'route_patterns']);

        foreach ($rows as $row) {
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

    /**
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
