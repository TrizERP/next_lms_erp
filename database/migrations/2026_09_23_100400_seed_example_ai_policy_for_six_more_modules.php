<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One worked example policy for the six modules registered by 2026_09_23_100000.
 *
 * WHY EACH ONE NEEDS ITS OWN
 *
 * A module with no policy opens its Policies tab on "No AI policies yet". That is a true
 * statement and a useless one: a person arriving at the tab has no way to tell what a
 * policy for that module would even say, so the tab reads as unfinished rather than as
 * empty. `ensureDefaultExamplePolicy()` in `AiPolicyController` already creates one
 * example, but it carries no module assignment, so it appears on the central console and on
 * no module tab at all. 2026_09_22_100400 did this for the nine modules that came before;
 * this is the same migration for the six that come after.
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
 * Not one text with the module's name substituted. A mobile-app policy is about not
 * reading configuration as usage; a timetable policy is about not judging a schedule the
 * table cannot describe; a medical policy is about not producing a clinical claim at all.
 * The rule toggles differ for the same reason — `use_ai_for_generating_answers` is ON for
 * Communication, where drafting a message for review is the whole job, and brainstorming
 * and rewriting are OFF for Student Medical, where composing prose around a child's
 * record is precisely what must not happen.
 *
 * NO RECORD APPEARS IN THIS FILE. No child, no message, no certificate number, and above
 * all no clinical detail. A policy says what the AI may do; it holds no data about anybody.
 *
 * THE MODULE ID IS RESOLVED AT RUNTIME, NEVER WRITTEN DOWN. `ai_modules` ids differ per
 * estate, and a literal here would scope a policy to whatever module happened to sit at
 * that number. A module with no row is skipped rather than guessed at.
 *
 * NO EXISTING MODULE IS TOUCHED. Every write is keyed on one of the six module keys below
 * and on a policy name that does not exist yet, so the nine AI Stacks that came before
 * keep the examples 2026_09_22_100400 gave them, and Fees keeps its own institute-scoped
 * policy exactly as it is.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_23_100400_seed_example_ai_policy_for_six_more_modules.php
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
            'mobile_apps' => [
                'name' => 'Mobile app configuration policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise how the mobile app home screens are configured and '
                    .'explain what a section or tile does. The tables hold configuration only — no '
                    .'session, device or login is recorded anywhere — so AI may never report downloads, '
                    .'installs, active users or adoption, and may never treat a tile being switched on as '
                    .'evidence that anybody has used it.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'student_icard' => [
                'name' => 'Student I-Card data policy',
                'type' => 'ai_assisted',
                'description' => 'AI may list which students are ready for a card and which are missing a '
                    .'photo, roll number or class. Only the fields a card prints are read — never the '
                    .'wider student file. A missing photo is a detail the office has not collected, not a '
                    .'child who may not have a card, and AI may say nothing about a student beyond the '
                    .'fields the card itself carries.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'certificate' => [
                'name' => 'Certificate issue policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise which certificates were issued, of which types and '
                    .'when. The printed certificate text is never given to a model, so AI may not quote '
                    .'or paraphrase what a certificate says about a child, and may not state that a '
                    .'certificate is valid, pending or approved — the register records issue only. '
                    .'Issuing a certificate remains a person\'s act on the Certificate screen.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                'overrides' => [],
            ],

            'easy_com' => [
                'name' => 'Communication reporting policy',
                'type' => 'ai_assisted',
                'description' => 'AI may summarise what the school has sent, per channel, and draft text '
                    .'for a person to review before sending. Only WhatsApp records a delivery outcome: for '
                    .'SMS and app notifications AI may never state or estimate that a message was '
                    .'received, read or opened, and may never report a reach or open rate. Sending is a '
                    .'person pressing the button.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Drafting a message for a person to review is what this module's AI is
                // for, so the writing rules are on rather than off.
                'overrides' => ['use_ai_for_generating_answers' => true],
            ],

            'timetable' => [
                'name' => 'Timetable interpretation policy',
                'type' => 'ai_assisted',
                'description' => 'AI may report the published timetable and the one conflict the records '
                    .'prove — a teacher booked into two different classes in the same period on the same '
                    .'day. The table records no room and no teacher availability, so AI may never say a '
                    .'timetable is balanced, fair or overloaded, never say a teacher is free at a given '
                    .'time, and never propose moving a period without every entry it would affect.',
                'disclosure' => 1,
                'acknowledgement' => 0,
                'overrides' => [],
            ],

            'student_medical' => [
                'name' => 'Student Medical data policy',
                'type' => 'ai_assisted',
                'description' => 'This is the most restricted policy in the product. AI may report '
                    .'administrative facts about infirmary visits — how many, when, which are still open, '
                    .'which doctor attended — and nothing more. It may never diagnose, suggest a '
                    .'diagnosis, name a condition not written in a record, describe a pattern across a '
                    .'child\'s visits, or say a student is unwell, frail or at risk. A student with no '
                    .'record has no record; that is never reported as healthy. Clinical detail is '
                    .'withheld from any summary covering more than one student, every output is read by a '
                    .'person before use, and no treatment, medication or exclusion is ever recommended. A '
                    .'clinical judgement is a clinician\'s.',
                'disclosure' => 1,
                'acknowledgement' => 1,
                // Everything a model could use to compose a clinical-sounding claim is off.
                // Summarising a count of visits needs none of them.
                'overrides' => [
                    'use_ai_for_generating_answers' => false,
                    'use_ai_for_brainstorming' => false,
                    'use_ai_for_rewriting' => false,
                ],
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
