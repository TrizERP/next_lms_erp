<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Inward, User I-Card, Petty Cash, Consent, Visitor Management and Transport as
 * AI modules.
 *
 * WHAT A MODULE NEEDS BEFORE IT CAN HAVE AN AI STACK
 *
 * A row in `ai_modules`. Without one, `TemplateModuleCatalog::exists()` answers no and the
 * Prompts and Templates tabs 404 with "that module is not one this school has";
 * `AiPolicyController` resolves no scope id and the Policies tab refuses to save; and
 * `fetchModuleUsage` reports the module unregistered. See 2026_09_22_100000 and
 * 2026_09_23_100000 for the eleven modules this is the third instalment of.
 *
 * TWO OF THE SIX ARE NOT CREATED — THEY ARE EXTENDED
 *
 * `inward_outward` and `transportation` have had rows since the workspace was seeded by
 * 2026_08_21_000001, carrying `/inward_outward/**` and `/Transportation/**`, which is
 * exactly where those two modules' screens live. Creating `inward` and `transport` rows
 * beside them would split each module across two keys: two policy scopes, two template
 * lists, two ledgers, and a question answered from whichever key the page happened to
 * resolve to. This is the same call `easy_com` got in 2026_09_23_100000 and for the same
 * reason.
 *
 * So the keys are `inward_outward` and `transportation`, the MENU slugs stay `inward-outward`
 * and `transport` — which is what `fees_menu_categories` already carries for both — and
 * the two rows gain the module route and the capability while keeping everything they had.
 *
 * WHY THE ROUTE PATTERNS ARE NARROW
 *
 * Four of these modules' screens live under another module's tree, and claiming the tree
 * would take that module's pages with them:
 *
 *   · Petty Cash, Consent and Visitor Management sit under `/admin-services/…`, which the
 *     admin-services module claims as `/admin-services/**`. Each page is named
 *     individually, and `RouteMatcher` scores a literal segment (10) above a wildcard (1),
 *     so `/admin-services/petty-cash` (20) beats `/admin-services/**` (11) for that page
 *     and every other admin-services page is untouched. PTM was given the same treatment
 *     in the same tree by 2026_09_22_100000.
 *   · User I-Card sits under `/student/…`, which the Students module claims as
 *     `/student/**`. Same treatment, same reason — and note that `/student/student_icard`
 *     belongs to the SEPARATE Student I-Card module registered in 2026_09_23_100000 and is
 *     not claimed here.
 *
 * NOTHING BELONGING TO ANY EXISTING MODULE IS MODIFIED. Every write is keyed on one of the
 * six module keys, and the two pre-existing rows are merged into — patterns added, never
 * replaced.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_24_100000_register_six_more_module_ai_stacks.php
 */
return new class extends Migration
{
    /**
     * The four modules with no `ai_modules` row yet. Inward and Transport are not among
     * them.
     *
     * `match_priority` 80 is what every module seeded by 2026_08_21_000001 carries.
     *
     * @var array<string, array{label:string, description:string, icon:string, sort_order:int}>
     */
    private const NEW_MODULES = [
        'user_icard' => [
            'label' => 'User I-Card',
            'description' => 'The staff a card can be printed for, and the fields a card carries.',
            'icon' => 'contact',
            'sort_order' => 290,
        ],
        'petty_cash' => [
            'label' => 'Petty Cash',
            'description' => 'Petty cash spends, the heads they are booked to and their totals.',
            'icon' => 'wallet',
            'sort_order' => 300,
        ],
        'consent' => [
            'label' => 'Consent',
            'description' => 'Consents raised for students and whether a decision has been recorded.',
            'icon' => 'file-check',
            'sort_order' => 310,
        ],
        'visitor_management' => [
            'label' => 'Visitor Management',
            'description' => 'Visits to the school, who they were for, and the times recorded at the gate.',
            'icon' => 'user-round-check',
            'sort_order' => 320,
        ],
    ];

    /**
     * The patterns each module's row must carry, added to whatever it already has.
     *
     * @var array<string, array<int, string>>
     */
    private const PATTERNS = [
        // Already carries /inward_outward and /inward_outward/**, which covers the
        // module's own AI Stack page at /inward_outward/ai-stack.
        'inward_outward' => ['/modules/inward-outward', '/modules/inward-outward/**'],

        'user_icard' => [
            '/modules/user-i-card',
            '/modules/user-i-card/**',
            // Named one page at a time, each more specific than the Students module's
            // `/student/**`. `/student/student_icard` is the separate Student I-Card
            // module and is deliberately absent.
            '/student/user_icard',
            '/student/user_icard/**',
            '/student/teacher_icard',
            '/student/teacher_icard/**',
        ],

        'petty_cash' => [
            '/modules/petty-cash',
            '/modules/petty-cash/**',
            // More specific than the admin-services module's `/admin-services/**`.
            '/admin-services/petty-cash',
            '/admin-services/petty-cash-master',
            '/admin-services/petty-cash-report',
            '/admin-services/petty-cash-ai-stack',
        ],

        'consent' => [
            '/modules/consent',
            '/modules/consent/**',
            '/admin-services/consent-master',
            '/admin-services/consent-report',
            '/admin-services/delete-consent-master',
            '/admin-services/consent-ai-stack',
        ],

        'visitor_management' => [
            '/modules/visitor-management',
            '/modules/visitor-management/**',
            '/admin-services/add-visitor',
            '/admin-services/visitor-report',
            '/admin-services/visitor-ai-stack',
            // NOT `/hostel/visitor-details` or `/hostel/visitor-report`. Those are the
            // Hostel module's own screens over its own `hostel_visitor_master` table, and
            // claiming them would take two of that module's pages and merge two registers
            // that are kept by different people.
        ],

        // Already carries /Transportation and /Transportation/**, which covers the
        // module's own AI Stack page at /Transportation/ai-stack.
        'transportation' => ['/modules/transport', '/modules/transport/**'],
    ];

    /**
     * The conversational chips the assistant offers on each module's pages.
     *
     * Every one is a question the module's own bound read tools can answer, and none names
     * a record: they are questions, not data.
     *
     * Several are deliberately phrased around what the tables DO record. The inward
     * register has no status column, the petty cash book has no approval, and the visitor
     * register's missing exit times are missing records — so the chips ask for the gap
     * rather than inviting a question the module would have to refuse.
     *
     * @var array<string, array<int, string>>
     */
    private const SUGGESTIONS = [
        'inward_outward' => [
            'What was received in the inward register this month?',
            'Which inward records have no physical file location recorded?',
            'Which inward records have no scan attached?',
        ],
        'user_icard' => [
            'Which staff are missing a photograph for their card?',
            'How many staff cards are ready to print?',
            'Which staff have no employee number recorded?',
        ],
        'petty_cash' => [
            'What has petty cash been spent on this month?',
            'Which heads account for the most petty cash spending?',
            'Which petty cash spends have no bill attached?',
        ],
        'consent' => [
            'Which consents have no decision recorded yet?',
            'How many consents were raised this year?',
            'Which consents are marked accountable?',
        ],
        'visitor_management' => [
            'Who visited the school today?',
            'Which visits have no exit time recorded?',
            'Which visitor types come most often?',
        ],
        'transportation' => [
            'Which buses have more students assigned than seats?',
            'What stops does each route call at?',
            'How many students are assigned to transport this year?',
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

        // Only the two pre-existing rows need their capability restored: the four created
        // below are deleted and their flags go with them.
        foreach (['inward_outward', 'transportation'] as $moduleKey) {
            $this->setCapabilities($moduleKey, ['generative' => false]);
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->whereIn('module_key', array_keys(self::SUGGESTIONS))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        // Only the four rows this migration created. `inward_outward` and `transportation`
        // were seeded by 2026_08_21_000001 and are left exactly as they were.
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
