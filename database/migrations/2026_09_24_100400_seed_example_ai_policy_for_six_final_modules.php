<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy for the six modules registered by 2026_09_24_100000.
 *
 * WHY EACH ONE NEEDS ITS OWN
 *
 * A module with no policy opens its Policies tab on "No AI policies yet". That is a true
 * statement and a useless one: a person arriving at the tab has no way to tell what a
 * policy for that module would even say, so the tab reads as unfinished rather than as
 * empty. 2026_09_22_100400 and 2026_09_23_100400 did this for the eleven modules that came
 * before; this is the same migration for the six that come after.
 *
 * These are real `ai_policies` rows with real `ai_policy_rules` and a real
 * `ai_policy_assignments` row pointing at the module's own `ai_modules` id. They are
 * created, read, edited and retired by exactly the code every other policy goes through.
 * Nothing about them is a frontend constant.
 *
 * PLATFORM-SCOPED, AND WHAT THAT MEANS
 *
 * Each row carries `sub_institute_id = null`, which is how a shared baseline is expressed
 * in this schema: `AiPolicyController::index()` matches `p.sub_institute_id = <institute>
 * OR p.sub_institute_id IS NULL`, so one row serves every school without any institute id
 * being named here. That is the point — a per-institute seed would have to pick an
 * institute, and picking one is the hardcoding this must not do.
 *
 * `is_example = 1` marks them, which the screens render as an "example" pill. Editing one
 * does not change it for everybody: the controller forks a platform policy into the
 * editing school's own copy, and the form says so before the save.
 *
 * EACH POLICY IS ABOUT ITS OWN MODULE, AND MOSTLY ABOUT A COLUMN THAT IS NOT THERE
 *
 * Four of these six exist to write down an absence, because the absence is what somebody
 * will otherwise ask the AI to fill in:
 *
 *   · Inward records no status, so nothing may call a document pending or closed.
 *   · Petty cash records no approval and no float, so nothing may be described as awaiting
 *     approval and no balance may be stated.
 *   · Consent records an empty decision, which means nobody has answered and never that a
 *     family refused.
 *   · The visitor register records a missing exit time, which means no exit was recorded
 *     and never that a named person is in the building.
 *
 * The rule toggles differ for the same reason. `use_ai_for_generating_answers` is ON for
 * Consent and Transport, where drafting a reminder or a route notice for somebody to
 * review is a real part of the job, and OFF everywhere else — most sharply for Petty Cash,
 * where a generated answer about money with no approval trail behind it is the thing to
 * avoid. Brainstorming and rewriting are off for User I-Card, where composing prose around
 * a staff record is not what a card needs.
 *
 * NO RECORD APPEARS IN THIS FILE. No child, no visitor, no amount, no staff member. A
 * policy says what the AI may do; it holds no data about anybody.
 *
 * THE MODULE ID IS RESOLVED AT RUNTIME, NEVER WRITTEN DOWN. `ai_modules` ids differ per
 * estate, and a literal here would scope a policy to whatever module happened to sit at
 * that number. A module with no row is skipped rather than guessed at.
 *
 * NO EXISTING MODULE IS TOUCHED. Every write is keyed on one of the six module keys below
 * and on a policy name that does not exist yet, so the eleven AI Stacks that came before
 * keep the examples they were given, and Fees keeps its own institute-scoped policy
 * exactly as it is.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_24_100400_seed_example_ai_policy_for_six_final_modules.php
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
     * `overrides` are merged over the baseline above. `disclosure` and `acknowledgement`
     * are the two flags that decide whether a person has to be told a machine wrote
     * something and whether they have to confirm they read that — both matter most on the
     * modules whose output reaches a family or commits the school to a figure.
     *
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        return [
            'inward_outward' => [
                'name' => 'Inward register policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise what has been received in the inward register and point '
                    .'out where the register itself has gaps — a record with no physical file location, or '
                    .'no scan attached. The register records no status, owner, due date or reply, so AI may '
                    .'never describe a document as pending, overdue, actioned, closed or answered, and may '
                    .'never present the age of a record as lateness.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'user_icard' => [
                'name' => 'Staff I-Card data policy',
                'type' => 'ai_assisted',
                'description' => 'AI may list which staff are ready for a card and which are missing a '
                    .'photograph, an employee number or a profile. Only the fields a card prints are read — '
                    .'never salary, bank, PAN, Aadhaar, provident fund or contract details, which the tools '
                    .'behind this module cannot return at all. The account expiry on a staff record is not '
                    .'a card expiry; this estate records no card issue date, expiry or print history, so AI '
                    .'may never say a card is due for renewal.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                // Composing prose around a staff record is not what a print list needs, and
                // a rewritten version of somebody's employment details is not an improvement
                // on the record.
                'overrides' => ['use_ai_for_brainstorming' => false, 'use_ai_for_rewriting' => false],
            ],

            'petty_cash' => [
                'name' => 'Petty cash reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise what petty cash has been spent on and which heads account '
                    .'for it, using only the totals the system has already calculated. This book records no '
                    .'approval of any kind and no opening float, top-up or reimbursement, so AI may never '
                    .'describe a transaction as awaiting approval, approved or rejected, and may never '
                    .'state a balance or how much is left. A missing bill is a missing document and never '
                    .'grounds for calling a transaction or a person irregular.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Off deliberately. A generated answer about money, on a book with no
                // approval trail behind it, is the one output here worth refusing.
                'overrides' => ['use_ai_for_generating_answers' => false],
            ],

            'consent' => [
                'name' => 'Consent handling policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which consents have been raised and which are still '
                    .'waiting for an answer, and may draft a reminder for a person to review and send. A '
                    .'consent with no decision recorded has NOT been refused — nobody has answered it — and '
                    .'AI may never report it as a decline, and never repeat a recorded decision in words '
                    .'other than the ones the office entered. This register records no expiry and no '
                    .'reminder history, so nothing may be described as expiring, lapsed or chased.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // A reminder to a family is a real deliverable here, and every prompt this
                // module publishes carries requires_review so a person reads it first.
                'overrides' => ['use_ai_for_generating_answers' => true],
            ],

            'visitor_management' => [
                'name' => 'Visitor register policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise who has visited, what for, and how consistently exit '
                    .'times are being recorded. A visit with no exit time means no exit was RECORDED: the '
                    .'visitor may still be on site or may have left without signing out, and AI may never '
                    .'state that a named person is currently in the building or produce a list described as '
                    .'who is on the premises. This register records no approval at all, and a visitor\'s '
                    .'phone number and email are there for the front desk and may not be repeated into any '
                    .'summary or message.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'transportation' => [
                'name' => 'Transport information policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise routes, stops, vehicles and who is assigned to them, and '
                    .'may report a bus with more students assigned than seats on one leg — the one '
                    .'judgement this data supports. The morning and afternoon legs are separate trips and '
                    .'may never be added together. Nothing records a boarding, a live position, a delay, or '
                    .'a vehicle\'s fitness, insurance or permit, so AI may never say a route is running or '
                    .'late, that a child travelled, or that a vehicle is roadworthy.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                // A stop or timing notice to families is a real deliverable, drafted for a
                // person to send.
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
