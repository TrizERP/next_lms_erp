<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Inventory, Front Desk, Task Management, Complaint, Utility and Document
 * Templates as AI modules.
 *
 * WHAT A MODULE NEEDS BEFORE IT CAN HAVE AN AI STACK
 *
 * A row in `ai_modules`. Without one, `TemplateModuleCatalog::exists()` answers no and the
 * Prompts and Templates tabs 404; `AiPolicyController` resolves no scope id and the
 * Policies tab refuses to save; and `fetchModuleUsage` reports the module unregistered.
 * See 2026_09_22_100000, 2026_09_23_100000 and 2026_09_24_100000 for the eighteen modules
 * this is the fourth and final instalment of.
 *
 * FOUR OF THE SIX ARE NOT CREATED — THEY ARE EXTENDED
 *
 * `inventory`, `front_desk`, `document-templates` and `migration-modules` have had rows
 * since the workspace was seeded by 2026_08_21_000001, and each already claims the tree
 * its screens live under. Creating second rows beside them would split each module across
 * two keys: two policy scopes, two template lists, two ledgers. This is the call `easy_com`
 * got in 2026_09_23_100000 and `inward_outward` and `transportation` got in
 * 2026_09_24_100000.
 *
 * TWO OF THOSE KEYS CARRY A HYPHEN, AND THAT IS NOT A TYPO
 *
 * `document-templates` and `migration-modules` are spelled with hyphens in `ai_modules`.
 * Everything downstream builds from the key verbatim — `agents.document-templates` is the
 * RBAC right, `k12.document-templates.summary` the template key — so the hyphen travels
 * with it. Writing either with an underscore would name a right nobody holds and a
 * template nothing reads.
 *
 * UTILITY IS KEYED `migration-modules`, AND IS NOT ABOUT UTILITIES
 *
 * That row has claimed `/Utility` and `/Utility/**` since the workspace was seeded. In this
 * ERP the Utility module is bulk data operations — student transfer, academic-year
 * rollover, breakoff rollover, bulk update and the custom-module builder. This estate holds
 * no electricity, water, gas, meter-reading, bill or consumption table of any kind; the
 * module registered here is the one that exists. Its menu slug stays `utility`.
 *
 * NOTHING BELONGING TO ANY EXISTING MODULE IS MODIFIED. Every write is keyed on one of the
 * six module keys, and the four pre-existing rows are merged into — patterns added, never
 * replaced.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_25_100000_register_final_six_module_ai_stacks.php
 */
return new class extends Migration
{
    /**
     * The two modules with no `ai_modules` row yet.
     *
     * `match_priority` 80 is what every module seeded by 2026_08_21_000001 carries.
     *
     * @var array<string, array{label:string, description:string, icon:string, sort_order:int}>
     */
    private const NEW_MODULES = [
        'task_management' => [
            'label' => 'Task Management',
            'description' => 'Tasks allocated to people, their dates and whether they are finished.',
            'icon' => 'list-checks',
            'sort_order' => 330,
        ],
        'complaint' => [
            'label' => 'Complaint',
            'description' => 'Complaints raised, the group they were assigned to and whether they are closed.',
            'icon' => 'message-square-warning',
            'sort_order' => 340,
        ],
    ];

    /**
     * The patterns each module's row must carry, added to whatever it already has.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        // Already carries /Inventory and /Inventory/**.
        'inventory' => ['/modules/inventory', '/modules/inventory/**'],

        // Already carries /front_desk and /front_desk/**. NOT extended into the pages
        // inside that tree which other modules own — /front_desk/circular belongs to
        // Circular and /front_desk/create-timetable to Time Table, both registered
        // earlier with literal patterns that already beat the wildcard.
        'front_desk' => ['/modules/front-desk', '/modules/front-desk/**'],

        'task_management' => [
            '/modules/task-management', '/modules/task-management/**',
            '/task-management', '/task-management/**',
        ],

        'complaint' => [
            '/modules/complaint', '/modules/complaint/**',
            // More specific than the admin-services module's `/admin-services/**`.
            '/admin-services/complaint-management',
            '/admin-services/complaint-report',
            '/admin-services/complaint-ai-stack',
        ],

        // Already carries /migration-modules, /migration-modules/** , /Utility and
        // /Utility/**. The menu slug is `utility`; the key is not.
        'migration-modules' => ['/modules/utility', '/modules/utility/**'],

        // Already carries /document-templates and /document-templates/**.
        'document-templates' => ['/modules/document-templates', '/modules/document-templates/**'],
    ];

    /**
     * The conversational chips the assistant offers on each module's pages.
     *
     * Every one is a question the module's own bound read tools can answer, and none names
     * a record.
     *
     * Several are phrased around what the tables DO record, because the obvious phrasing
     * would invite an answer the module has to refuse: the inventory chips ask about the
     * RECORDED figure rather than "what is in stock", the complaint chips do not ask about
     * priority or escalation, and the Utility chips do not mention a bill.
     *
     * @var array<string, array<int, string>>
     */
    private const SUGGESTIONS = [
        'inventory' => [
            'Which items are at or below their recorded reorder level?',
            'What requisitions are waiting for an approval date?',
            'What purchase orders have been raised this year?',
        ],
        'front_desk' => [
            'Who came to the front desk this week?',
            'Which front desk visits have no exit time recorded?',
            'Which members of staff were visitors here to meet?',
        ],
        'task_management' => [
            'Which tasks are overdue?',
            'What tasks are assigned to this person?',
            'How many tasks are finished and how many are still open?',
        ],
        'complaint' => [
            'Which complaints are still open?',
            'How many complaints were raised this year?',
            'Which group has the most complaints assigned to it?',
        ],
        'migration-modules' => [
            'What custom modules have been defined here?',
            'Which academic years have enrolments recorded against them?',
            'Which institutes could a student be transferred to?',
        ],
        'document-templates' => [
            'What document templates exist for this school?',
            'Which templates are still drafts?',
            'What merge fields does this template use?',
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

        // Only the four pre-existing rows need their capability restored: the two created
        // below are deleted and their flags go with them.
        foreach (['inventory', 'front_desk', 'migration-modules', 'document-templates'] as $moduleKey) {
            $this->setCapabilities($moduleKey, ['generative' => false]);
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
