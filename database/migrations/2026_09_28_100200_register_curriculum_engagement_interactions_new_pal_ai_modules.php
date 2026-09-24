<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Curriculum Planning, Engagement, Interactions and New PAL as AI modules.
 *
 * Same pattern as 2026_09_22_100000_register_exam_ptm_hostel_request_circular_ai_modules.php
 * — see that file for why a row is needed, why route patterns matter, and why writes are
 * merged rather than replaced.
 *
 * WHAT WAS TRUE BEFORE THIS MIGRATION, PER check_ai_stack_coverage.php
 *
 *   'engagement' => 'no engagement table in this estate',
 *   'interactions' => 'no interactions table in this estate',
 *   'curriculum-planning' => 'no curriculum planning table in this estate',
 *   'new-pal' => 'personalised learning, served by the pal module',
 *
 * The first three are now wrong in the way that matters: Engagement is grounded in real
 * attendance/homework/assignment records (computed live, nothing stored — see
 * EngagementReportService); Interactions is grounded in the new `interaction_logs` table
 * added alongside this migration; Curriculum Planning was always backed by real data
 * (`lms_curriculum` and its joined tables) — it simply had no `ai_modules` row of its own
 * because it lived as a tab inside `lms`. New PAL gets its own row so `/pal/new/**`
 * resolves to its own tools instead of falling back to the older `pal` module — the
 * "served by the pal module" line was, until now, a real instance of one module's AI
 * Stack silently showing another module's content.
 *
 * check_ai_stack_coverage.php's own `$deliberatelyUnbound` list is updated in the same
 * change that adds this migration, removing all four lines above.
 *
 * NOTHING BELONGING TO `lms` OR `pal` IS MODIFIED. Curriculum Planning's pages stay at
 * `/lms/curriculum-planning`; New PAL's stay at `/pal/new`. Neither `lms` nor `pal`'s own
 * `ai_modules` row, route patterns or capabilities are touched.
 *
 * Run it on its own:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100200_register_curriculum_engagement_interactions_new_pal_ai_modules.php
 */
return new class extends Migration
{
    /**
     * @var array<string, array{label:string, description:string, route_patterns:array<int,string>, icon:string, sort_order:int}>
     */
    private const MODULES = [
        // Route patterns list BOTH the shared category route the seeded menu row actually
        // points at (`fees_menu_categories.route`, confirmed by reading the live rows
        // rather than assumed — `/modules/<slug>/ai-stack`) and the module's own real
        // pages, the same way Exam carries both `/modules/exam/**` and `/exam/**`.
        'curriculum_planning' => [
            'label' => 'Curriculum Planning',
            'description' => 'Curriculum, units, chapters and learning outcomes, and their coverage.',
            'route_patterns' => [
                '/modules/curriculum-planning', '/modules/curriculum-planning/**',
                '/lms/curriculum-planning', '/lms/curriculum-planning/**',
            ],
            'icon' => 'clipboard-list',
            'sort_order' => 250,
        ],
        'engagement' => [
            'label' => 'Engagement',
            'description' => 'Student engagement, computed from attendance, homework and assignment activity.',
            'route_patterns' => [
                '/modules/engagement', '/modules/engagement/**',
                '/engagement', '/engagement/**',
            ],
            'icon' => 'users',
            'sort_order' => 260,
        ],
        'interactions' => [
            'label' => 'Interactions',
            'description' => 'Logged touchpoints with students, parents and staff, and their follow-ups.',
            'route_patterns' => [
                '/modules/interactions', '/modules/interactions/**',
                '/interactions', '/interactions/**',
            ],
            'icon' => 'message-circle',
            'sort_order' => 270,
        ],
        'new_pal' => [
            'label' => 'New PAL',
            'description' => 'Personalised learning: content model, gamification and coherence mapping.',
            'route_patterns' => [
                '/modules/new-pal', '/modules/new-pal/**',
                '/pal/new', '/pal/new/**',
            ],
            'icon' => 'brain',
            'sort_order' => 280,
        ],
    ];

    private const SUGGESTIONS = [
        'curriculum_planning' => [
            'Show the current curriculum plan for this class.',
            'Which units are behind on planned periods this term?',
            'Summarise curriculum coverage for this subject.',
        ],
        'engagement' => [
            'Which students have low engagement this month?',
            'Summarise engagement for this class.',
            'Which students need an engagement follow-up?',
        ],
        'interactions' => [
            'Show recent interactions this week.',
            'Which follow-ups are still open?',
            'Summarise this month’s interactions by type.',
        ],
        'new_pal' => [
            'Show students who need personalised learning support.',
            'Summarise this week’s PAL learning events.',
            'Which chapters have gaps in the content model?',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        foreach (self::MODULES as $moduleKey => $module) {
            $existing = DB::table('ai_modules')
                ->where('module_key', $moduleKey)
                ->whereNull('sub_institute_id')
                ->first();

            if ($existing === null) {
                DB::table('ai_modules')->insert([
                    'module_key' => $moduleKey,
                    'label' => $module['label'],
                    'domain' => 'k12',
                    'description' => $module['description'],
                    'route_patterns' => json_encode($module['route_patterns']),
                    'entity_key' => null,
                    'entity_param' => null,
                    'capabilities' => json_encode([
                        'conversational' => true,
                        'generative' => true,
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
            } else {
                $patterns = json_decode((string) $existing->route_patterns, true);
                $patterns = is_array($patterns) ? $patterns : [];

                $capabilities = json_decode((string) $existing->capabilities, true);
                $capabilities = is_array($capabilities) ? $capabilities : [];

                DB::table('ai_modules')->where('id', $existing->id)->update([
                    'route_patterns' => json_encode(array_values(array_unique(array_merge($patterns, $module['route_patterns'])))),
                    'capabilities' => json_encode(array_merge($capabilities, ['conversational' => true, 'generative' => true])),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->seedSuggestions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        if (Schema::hasTable('ai_suggestions')) {
            DB::table('ai_suggestions')
                ->whereIn('module_key', array_keys(self::MODULES))
                ->whereNull('sub_institute_id')
                ->delete();
        }

        DB::table('ai_modules')
            ->whereIn('module_key', array_keys(self::MODULES))
            ->whereNull('sub_institute_id')
            ->delete();
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
