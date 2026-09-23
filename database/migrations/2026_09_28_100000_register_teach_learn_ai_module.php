<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers Teach/Learn as an AI module.
 *
 * Teach/Learn (`app/teach-learn` in the Next app) is a teacher-facing presentation layer
 * over the existing LMS course/chapter catalogue — the same records `lms` already reads.
 * It had no `ai_modules` row, so `check_ai_stack_coverage.php` listed it deliberately
 * unbound: "keeps its own pages outside the shared category route". This migration gives
 * it one, following the pattern 2026_09_22_100000 set for Exam/PTM/Hostel/Student
 * Request/Circular.
 *
 * NOTHING BELONGING TO `lms` IS MODIFIED. Teach/Learn's tool bindings (added to
 * config/ai.php in the same change) point at the same `lms.courses`/`lms.activities`
 * backend tools `lms` uses — deliberate reuse of real data across two modules' pages,
 * not a fork of it. `lms`'s own row, route patterns and capabilities are untouched.
 *
 * Run it on its own:
 *
 *   php artisan migrate --path=database/migrations/2026_09_28_100000_register_teach_learn_ai_module.php
 */
return new class extends Migration
{
    private const MODULE_KEY = 'teach_learn';

    private const ROUTE_PATTERNS = ['/teach-learn', '/teach-learn/**'];

    private const SUGGESTIONS = [
        'Show the current lessons for this class.',
        'Which chapters have low content coverage this term?',
        'Summarise this week\'s learning activities.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_modules')) {
            return;
        }

        $exists = DB::table('ai_modules')
            ->where('module_key', self::MODULE_KEY)
            ->whereNull('sub_institute_id')
            ->exists();

        if (! $exists) {
            DB::table('ai_modules')->insert([
                'module_key' => self::MODULE_KEY,
                'label' => 'Teach/Learn',
                'domain' => 'k12',
                'description' => 'Courses, chapters and learning activities, as teachers and learners see them.',
                'route_patterns' => json_encode(self::ROUTE_PATTERNS),
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
                'icon' => 'graduation-cap',
                'sort_order' => 240,
                'match_priority' => 80,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // A row already exists (e.g. re-run after a partial rollback) — merge patterns
            // and turn generative on, the same way 2026_09_22_100000 merges into existing rows.
            $rows = DB::table('ai_modules')
                ->where('module_key', self::MODULE_KEY)
                ->whereNull('sub_institute_id')
                ->get(['id', 'route_patterns', 'capabilities']);

            foreach ($rows as $row) {
                $patterns = json_decode((string) $row->route_patterns, true);
                $patterns = is_array($patterns) ? $patterns : [];

                $capabilities = json_decode((string) $row->capabilities, true);
                $capabilities = is_array($capabilities) ? $capabilities : [];

                DB::table('ai_modules')->where('id', $row->id)->update([
                    'route_patterns' => json_encode(array_values(array_unique(array_merge($patterns, self::ROUTE_PATTERNS)))),
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
                ->where('module_key', self::MODULE_KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        DB::table('ai_modules')
            ->where('module_key', self::MODULE_KEY)
            ->whereNull('sub_institute_id')
            ->delete();
    }

    private function seedSuggestions(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $sort = 10;

        foreach (self::SUGGESTIONS as $prompt) {
            $exists = DB::table('ai_suggestions')
                ->where('module_key', self::MODULE_KEY)
                ->where('prompt', $prompt)
                ->whereNull('sub_institute_id')
                ->exists();

            if (! $exists) {
                DB::table('ai_suggestions')->insert([
                    'module_key' => self::MODULE_KEY,
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
};
