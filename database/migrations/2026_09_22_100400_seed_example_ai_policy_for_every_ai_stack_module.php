<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy for every module that has an AI Stack.
 *
 * WHY EVERY MODULE NEEDED ONE
 *
 * Eight of the nine AI Stack modules opened their Policies tab on "No AI policies yet".
 * That is a true statement and a useless one: a person arriving at the tab has no way to
 * tell what a policy for that module would even say, so the tab reads as unfinished rather
 * than as empty. `ensureDefaultExamplePolicy()` in `AiPolicyController` already creates one
 * example, but it carries no module assignment, so it appears on the central console and on
 * no module tab at all.
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
 * EACH POLICY IS ABOUT ITS OWN MODULE
 *
 * Not one text with the module's name substituted. A fee policy is about money leaving a
 * family's account; a PTM policy is about not reading an unsaved register as an absence; a
 * hostel policy is about not inventing a bed count. The rule toggles differ for the same
 * reason — `use_ai_for_generating_answers` is off for Exam in a way it need not be for
 * Circular, where drafting a notice is the whole job.
 *
 * NO RECORD APPEARS IN THIS FILE. No child, no family, no meeting, no room, no amount.
 * A policy says what the AI may do; it holds no data about anybody.
 *
 * THE MODULE ID IS RESOLVED AT RUNTIME, NEVER WRITTEN DOWN. `ai_modules` ids differ per
 * estate, and a literal here would scope a policy to whatever module happened to sit at
 * that number. A module with no row is skipped rather than guessed at.
 *
 * FEES IS NOT TOUCHED beyond gaining an example of its own: its existing institute-scoped
 * policy keeps its id, its assignment and its rules, and the example sits beside it.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_22_100400_seed_example_ai_policy_for_every_ai_stack_module.php
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
     * modules whose output reaches a family.
     *
     * @return array<string, array<string, mixed>>
     */
    private function policies(): array
    {
        return [
            'fees' => [
                'name' => 'Fee communication policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise fee records, explain what a balance is made of, and draft '
                    .'reminders for a person to review. It may not state an amount, a due date or a receipt '
                    .'number that is not in the records, and every message about money is read by a person '
                    .'before it reaches a family.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'attendance' => [
                'name' => 'Attendance interpretation policy',
                'type' => 'ai_assisted',
                'description' => 'AI may report attendance rates from the marked register and draft notes home '
                    .'for review. A day nobody has coded is not an absence: uncoded days are excluded from a '
                    .'rate rather than counted against a child. AI may never state a reason for an absence, '
                    .'because the register does not record one.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'admissions' => [
                'name' => 'Admission enquiry policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise the enquiry pipeline and draft follow-ups to families for a '
                    .'person to review. An enquiry records that a family asked about a place and nothing about '
                    .'the outcome, so AI may never promise a seat, imply one is about to be lost, or state an '
                    .'admission decision.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'students' => [
                'name' => 'Student record policy',
                'type' => 'ai_assisted',
                'description' => 'AI may list who is enrolled where, report cohort figures and draft requests to '
                    .'families for a missing detail. The directory records where a child sits and how to reach '
                    .'their family, and nothing about how that child is doing, so AI may never assess a '
                    .'student for ability, behaviour or character.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'exam' => [
                'name' => 'Exam result interpretation policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise recorded marks and explain what a set of results does and does '
                    .'not show. An absence carries no score and must never be treated as a zero or averaged in '
                    .'as one. A mark records one performance on one day, so AI may never characterise a student '
                    .'as able, weak, lazy or gifted from it.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Emphatic here in a way it is not elsewhere: an exam module is the one
                // place "generate an answer" has an obvious and wrong reading.
                'overrides' => ['use_ai_for_generating_answers' => false, 'use_ai_for_completing_assignments' => false],
            ],

            'ptm' => [
                'name' => 'PTM attendance interpretation policy',
                'type' => 'ai_assisted',
                'description' => 'If PTM attendance is empty or not recorded, classify it as "Not Recorded". Do '
                    .'not interpret an empty attendance value as parent absence. The register holds three '
                    .'states — Attended, Not Attended, and Not Recorded — and AI must preserve all three. A '
                    .'booking whose register a teacher has not yet saved is a record waiting to be completed, '
                    .'not a family that stayed away. AI may summarise take-up and draft invitations for a '
                    .'person to review, and may never state why a parent did not attend.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'hostel' => [
                'name' => 'Hostel occupancy reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may report which rooms are occupied and who is allocated where. Rooms carry '
                    .'no recorded bed capacity in this system, so AI may never say a hostel is full, has space, '
                    .'or is any percentage occupied. An allocation naming a room the institute does not own is '
                    .'a record to be corrected, not an occupant to be doubted.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'student_request' => [
                'name' => 'Student request handling policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise the request queue, say what a request is still missing, and '
                    .'draft an acknowledgement for a person to review. A pending request has not been refused: '
                    .'AI may never state a decision, predict one, or recommend approving or refusing a request. '
                    .'Deciding is a person\'s act on the Student Request screen.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'circular' => [
                'name' => 'Circular drafting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may draft the body of a circular from a subject and a few points, and may '
                    .'summarise what has been published. The register records publication and holds no read '
                    .'receipt, so AI may never state how many families received, opened or read a circular. '
                    .'Publishing sends a notice to every family in a class and stays a person pressing the '
                    .'button.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                // Drafting prose for a person to publish is what this module's AI is for,
                // so the writing rules are on rather than off.
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
