<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy for the six modules bound by 2026_09_26_100000.
 *
 * WHY EACH ONE NEEDS ITS OWN
 *
 * A module with no policy opens its Policies tab on "No AI policies yet" — a true
 * statement and a useless one. The four migrations before this did the same for the
 * twenty-three modules that came earlier; this completes the set, so that every module in
 * the ERP menu with an AI Stack has a worked example behind its first tab.
 *
 * PLATFORM-SCOPED, AND WHAT THAT MEANS
 *
 * Each row carries `sub_institute_id = null`, which is how a shared baseline is expressed
 * in this schema: `AiPolicyController::index()` matches `p.sub_institute_id = <institute>
 * OR p.sub_institute_id IS NULL`, so one row serves every school without any institute id
 * being named here.
 *
 * EACH POLICY NAMES WHAT ITS MODULE MUST NOT CLAIM
 *
 *   · Parent Communication is the inbound direction, and an unanswered message means
 *     nobody has replied — never that the school refused.
 *   · SQAA scores nothing; no rubric or grade boundary exists anywhere.
 *   · Users reads account fields only, and the last login is the only activity recorded —
 *     so nothing may judge how much anybody works.
 *   · Library records no fine, reservation or renewal.
 *   · LMS records configuration, not achievement.
 *   · Institute records the shape of the school, not its people.
 *
 * `use_ai_for_generating_answers` is ON only for Parent Communication, where drafting a
 * reply for somebody to review and send is the module's actual job. It is OFF for Users
 * most deliberately of all: a generated answer about named colleagues' activity, on a
 * table that records one timestamp, is the output here worth refusing.
 *
 * NO RECORD APPEARS IN THIS FILE. A policy says what the AI may do; it holds no data.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_26_100400_seed_example_ai_policy_for_the_remaining_modules.php
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
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        return [
            'parent_communication' => [
                'name' => 'Parent message handling policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which messages parents have sent and which are still '
                    .'waiting for a reply, and may draft a reply for a person to review and send. This is '
                    .'the inbound direction and is not the Communication module: the two must never be '
                    .'combined into one total. A message with no reply means NOBODY HAS ANSWERED IT YET — '
                    .'never a refusal — and the body of a named family\'s letter may not be quoted or '
                    .'paraphrased into anything a wider audience will read.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Drafting a reply for review is this module's real deliverable.
                'overrides' => ['use_ai_for_generating_answers' => true],
            ],

            'sqaa' => [
                'name' => 'Quality assurance evidence policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise the quality assurance criteria and the evidence '
                    .'uploaded against them, and may point out where evidence is marked available but '
                    .'carries no file. It may NOT score the school: no rubric, weighting or grade '
                    .'boundary is recorded anywhere, so AI may never state a score, rating, band or '
                    .'readiness for assessment, and may never judge whether a piece of evidence is good '
                    .'enough. The number of document slots must always be reported beside the number of '
                    .'uploads.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'user' => [
                'name' => 'User account reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which ERP accounts exist, what profiles they sit under '
                    .'and which have never recorded a login. Only account fields are readable — never '
                    .'salary, bank, PAN, Aadhaar or contract details. The last login is the ONLY activity '
                    .'this system records, so AI may never describe how much anybody uses the system, '
                    .'rank colleagues, or suggest that anybody is not doing their work. A missing last '
                    .'login is a missing record, not proof that somebody has never signed in.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Off deliberately. A generated answer about named colleagues' activity,
                // on a table that records one timestamp, is the output worth refusing.
                'overrides' => ['use_ai_for_generating_answers' => false],
            ],

            'library' => [
                'name' => 'Library circulation policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise the catalogue and the loans against it, and may report '
                    .'which loans are out and which are past their due date — both exact derivations from '
                    .'recorded columns. One catalogue row is a TITLE and not a book on the shelf. This '
                    .'system records no fine, reservation or renewal, so AI may never state what a '
                    .'borrower owes, and may never describe a named child as an unreliable borrower: an '
                    .'overdue book is an overdue book.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'lms' => [
                'name' => 'Course configuration policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which courses are configured and what activities are '
                    .'recorded against them. These records are CONFIGURATION, not achievement: AI may '
                    .'never state what a child learned, how well anybody did, or that a course was '
                    .'effective, and may never compare teachers, classes or subjects by course count. A '
                    .'course with no activity recorded is a course with no activity recorded, not a '
                    .'neglected one.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'institute' => [
                'name' => 'Institute structure policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise how the institute is structured into sections, '
                    .'standards and divisions, and which departments exist. This is the SHAPE of the '
                    .'school and not its people: AI may never state how many students or staff are in '
                    .'anything it was not given a figure for, may never name anybody, and may never judge '
                    .'the structure as good, efficient or appropriate.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
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
