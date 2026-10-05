<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy for the six modules registered by 2026_09_25_100000.
 *
 * WHY EACH ONE NEEDS ITS OWN
 *
 * A module with no policy opens its Policies tab on "No AI policies yet" — a true statement
 * and a useless one, because a person arriving there has no way to tell what a policy for
 * that module would even say. The three migrations before this did the same for the
 * seventeen modules that came earlier.
 *
 * These are real `ai_policies` rows with real `ai_policy_rules` and a real
 * `ai_policy_assignments` row pointing at the module's own `ai_modules` id, created and
 * edited by exactly the code every other policy goes through.
 *
 * PLATFORM-SCOPED, AND WHAT THAT MEANS
 *
 * Each row carries `sub_institute_id = null`, which is how a shared baseline is expressed
 * in this schema: `AiPolicyController::index()` matches `p.sub_institute_id = <institute>
 * OR p.sub_institute_id IS NULL`, so one row serves every school without any institute id
 * being named here — a per-institute seed would have to pick one, and picking one is the
 * hardcoding this must not do.
 *
 * EACH POLICY IS ABOUT A COLUMN THAT IS NOT WHAT ITS NAME SAYS
 *
 * More than in any previous batch, these policies exist to write down an absence:
 *
 *   · Inventory's stock column is never decreased when stock is issued, so nothing may
 *     describe what is on a shelf or recommend an order.
 *   · The complaint "solution" column holds a status word; there is no resolution text,
 *     no priority, no SLA and no escalation.
 *   · Utility is bulk data operations, not electricity and water — and it records no
 *     history of any operation it performs.
 *   · The front desk register is not the school's whole visitor log.
 *   · The task status column has two spellings of "complete".
 *   · The document template tables are empty, and an empty list must not be filled in.
 *
 * The rule toggles differ for the same reason. `use_ai_for_generating_answers` is ON only
 * for Document Templates, where drafting template content for somebody to review is the
 * module's actual job, and OFF everywhere else — most sharply for Inventory, where a
 * generated answer about stock levels is precisely what the data cannot support.
 *
 * NO RECORD APPEARS IN THIS FILE. A policy says what the AI may do; it holds no data.
 *
 * THE MODULE ID IS RESOLVED AT RUNTIME, NEVER WRITTEN DOWN. A module with no row is
 * skipped rather than guessed at.
 *
 * NO EXISTING MODULE IS TOUCHED. Every write is keyed on one of the six module keys below
 * and on a policy name that does not exist yet.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_25_100400_seed_example_ai_policy_for_the_last_six_modules.php
 */
return new class extends Migration
{
    /**
     * The rule keys `AiPolicyResolver::ruleCatalogue()` publishes.
     *
     * Listed so a policy below can name the handful it changes and inherit the rest,
     * rather than nine booleans per module of which two are interesting.
     */
    private const PERMISSIVE_BASELINE = [
        'use_ai_for_brainstorming' => true,
        'use_ai_for_grammar_spelling' => true,
        'use_ai_for_explanations' => true,
        'use_ai_for_summarization' => true,
        'use_ai_for_rewriting' => true,
        'use_ai_for_generating_answers' => false,
        'use_ai_for_generating_code' => false,
        'use_ai_for_generating_images' => false,
        'use_ai_for_completing_assignments' => false,
    ];

    /**
     * module_key => the example policy for that module.
     *
     * `overrides` are merged over the baseline above. Two of the keys carry a hyphen
     * because that is how `ai_modules` spells them, and `migration-modules` is the Utility
     * module.
     *
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        return [
            'inventory' => [
                'name' => 'Inventory stock reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may list items, requisitions and purchase orders, and may report which '
                    .'items are at or below their recorded reorder level. It may NOT describe what is on '
                    .'the shelf: this system keeps no running stock balance — the stock column is '
                    .'increased by a purchase and never decreased when stock is issued — so AI may never '
                    .'say an item is in stock, out of stock, low or sufficient, and may never recommend an '
                    .'order quantity. Vendor bank, PAN and registration details are not readable at all.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Off deliberately. A generated answer about stock levels, on a figure that
                // cannot be made accurate, is the one output here worth refusing.
                'overrides' => ['use_ai_for_generating_answers' => false],
            ],

            'front_desk' => [
                'name' => 'Front desk register policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise who came to the front desk, who they came to meet and '
                    .'how consistently exit times are recorded. A missing exit time means no exit was '
                    .'RECORDED, so AI may never state that a named person is in the building. This '
                    .'register is not the school\'s whole visitor log — a separate one belongs to Visitor '
                    .'Management — so an empty result may never be reported as nobody having visited, and '
                    .'a reader restricted to their own visits may never be shown that list as the whole '
                    .'day.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'task_management' => [
                'name' => 'Task reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which tasks are open, finished or past their date, using '
                    .'the normalised status only — the column holds two spellings of "complete" and a '
                    .'count matching one of them is wrong. A task with no date is undated, not overdue. '
                    .'Nothing records why a task is late or whether its date was agreed, so AI may never '
                    .'attribute a delay to a person, describe anybody as behind or underperforming, or '
                    .'rank people by task count.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'complaint' => [
                'name' => 'Complaint handling policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which complaints are open and closed and which group '
                    .'they sit with. The column named COMPLAINT_SOLUTION is the STATUS field and no '
                    .'resolution text exists anywhere, so AI may never say how a complaint was resolved or '
                    .'what was said to the person who raised it. This table records no priority, severity, '
                    .'due date, SLA or escalation, so AI may never call a complaint urgent or in need of '
                    .'escalation, and may never rank complaints. A complaint names the person who made it, '
                    .'and that name does not belong in a summary others will read.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'migration-modules' => [
                'name' => 'Utility data operations policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise the custom modules defined here and the academic years '
                    .'and institutes a rollover or student transfer would act on. This module is BULK DATA '
                    .'OPERATIONS, not utilities: this system records no electricity, water, gas, meter '
                    .'reading, bill or consumption anywhere, and AI must say so plainly rather than '
                    .'estimating any of them. It also records NO operation history, so AI may never state '
                    .'that a rollover or transfer has been run, when, or how many students moved.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'document-templates' => [
                'name' => 'Document template drafting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which templates exist, which are drafts and which merge '
                    .'fields a template contains, and may draft template content for a person to review '
                    .'before it is saved or published. It may not quote a stored document body it has not '
                    .'been given, and where no templates exist it must say exactly that rather than '
                    .'describing templates a school might want. This table records no reviewer and no '
                    .'approval, so AI may never say a template was approved, by whom, or when it went '
                    .'live, and may never publish one.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Drafting template content for review is this module's actual job.
                'overrides' => ['use_ai_for_generating_answers' => true],
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_policies')
            || ! Schema::hasTable('ai_policy_rules')
            || ! Schema::hasTable('ai_policy_assignments')
            || ! Schema::hasTable('ai_modules')) {
            return;
        }

        foreach ($this->policies() as $moduleKey => $policy) {
            $moduleId = $this->platformModuleId($moduleKey);

            if ($moduleId === null) {
                // The module is not registered on this estate. A policy with no module
                // assignment would sit on the central console belonging to nothing, which
                // is worse than not being seeded.
                continue;
            }

            $existing = DB::table('ai_policies')
                ->where('name', $policy['name'])
                ->whereNull('sub_institute_id')
                ->where('is_example', 1)
                ->value('id');

            if ($existing !== null) {
                // Idempotent. The row is left exactly as it is rather than reset, because a
                // school may have adjusted the shared example deliberately.
                continue;
            }

            $policyId = DB::table('ai_policies')->insertGetId([
                'sub_institute_id' => null,
                'name' => $policy['name'],
                'description' => $policy['description'],
                'policy_type' => $policy['type'],
                'is_example' => 1,
                'status' => 1,
                'require_disclosure' => $policy['disclosure'],
                'require_acknowledgement' => $policy['acknowledgement'],
                'ai_detection_required' => 0,
                'plagiarism_check_required' => 0,
                'detection_provider' => null,
                'detection_threshold' => null,
                // Nobody created it. A user id here would attribute a platform baseline to
                // whichever person happened to run the migration.
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_merge(self::PERMISSIVE_BASELINE, $policy['overrides']) as $rule => $enabled) {
                DB::table('ai_policy_rules')->insert([
                    'policy_id' => $policyId,
                    'rule_key' => $rule,
                    'rule_value' => $enabled ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('ai_policy_assignments')->insert([
                'policy_id' => $policyId,
                'scope_type' => 'module',
                'scope_id' => $moduleId,
                // Null like the policy itself, so every school resolves the assignment.
                // `index()` filters assignments on scope only, never on institute.
                'sub_institute_id' => null,
                'status' => 1,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_policies')) {
            return;
        }

        $ids = DB::table('ai_policies')
            ->whereIn('name', array_column($this->policies(), 'name'))
            ->whereNull('sub_institute_id')
            ->where('is_example', 1)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Rules and assignments are keyed by policy id; leaving them behind would attach
        // configuration to a row that no longer exists.
        foreach (['ai_policy_rules', 'ai_policy_assignments'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('policy_id', $ids)->delete();
            }
        }

        // Only the platform examples. A school that customised one holds its own forked
        // row, which carries a `sub_institute_id` and is excluded by the query above —
        // that copy is the school's work and is not this migration's to delete.
        DB::table('ai_policies')->whereIn('id', $ids)->delete();
    }

    /**
     * The platform `ai_modules` id for one module key, or null when it has no row.
     *
     * Platform only. A school's own row shadows the platform one for that school, but a
     * shared example has to hang off the shared row or it would appear for one institute
     * and vanish for the rest.
     */
    private function platformModuleId(string $moduleKey): ?int
    {
        $id = DB::table('ai_modules')
            ->where('module_key', $moduleKey)
            ->whereNull('sub_institute_id')
            ->where('status', 1)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
};
