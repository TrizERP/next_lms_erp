<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role-gates the suggestions that need rights a student does not have.
 *
 * The 000012 seed left `allowed_roles` null on every row, meaning "any role". That was
 * wrong for the analysis-flavoured prompts: a Student profile has no
 * `lms:analysis:read`, so "Explain this dashboard" and "Which KPI needs attention?"
 * were offered to students and then refused by the adapter the moment they were
 * clicked. The panel should never be where a user discovers they are not allowed to do
 * something — CapabilityResolver filters on exactly this column, so the fix is data.
 *
 * Roles here are the values McpAuth resolves, not LMS profile names:
 *   student · staff (which includes teachers) · admin
 *
 * Students are not left with an empty dashboard tab — they get prompts scoped to the
 * rights they do hold (`lms:dashboard:read`, `lms:activity:read`, `lms:result:read`,
 * `lms:attendance:read`).
 *
 * Idempotent; touches only baseline rows.
 */
return new class extends Migration
{
    /** Suggestions that need analysis or institute-wide rights. */
    private const STAFF_ONLY = [
        ['dashboard', 'Explain this dashboard'],
        ['dashboard', 'Which KPI needs attention?'],
        ['students', 'Which students need intervention?'],
        ['students', 'Search for a student'],
        ['fees', 'Which students have pending fees?'],
        ['fees', 'Fee collection summary'],
        ['fees', 'Show defaulters'],
        ['attendance', "Today's attendance"],
        ['attendance', 'Who has low attendance?'],
        ['admissions', 'Pending admissions'],
        ['admissions', 'Recent enquiries'],
        ['exam', 'Show result report'],
        ['exam', 'Which subjects are weakest?'],
        // Every student-page action reads or writes about a named student.
        ['student', 'Why is this student at risk?'],
        ['student', 'Show academic evidence'],
        ['student', 'Summarize performance'],
        ['student', 'What should we do next?'],
    ];

    /** Prompts a student can actually run, so their dashboard tab is not empty. */
    private const STUDENT_PROMPTS = [
        ['dashboard', 'Show my attendance', 'Show my attendance record.', 10],
        ['dashboard', 'Show my results', 'Show my latest assessment results.', 20],
        ['dashboard', 'My homework', 'Show my homework updates.', 30],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        $staffAndAdmin = json_encode(['staff', 'admin']);

        foreach (self::STAFF_ONLY as [$module, $label]) {
            DB::table('ai_suggestions')
                ->whereNull('sub_institute_id')
                ->where('module_key', $module)
                ->where('label', $label)
                ->update(['allowed_roles' => $staffAndAdmin, 'updated_at' => now()]);
        }

        // Agent, workflow and ontology suggestions are analysis and action surfaces
        // by nature. AiContextService already strips those tabs for students, but the
        // rows should say so too rather than relying on a single guard.
        DB::table('ai_suggestions')
            ->whereNull('sub_institute_id')
            ->whereIn('capability', ['agent', 'workflow', 'ontology'])
            ->update(['allowed_roles' => $staffAndAdmin, 'updated_at' => now()]);

        // Generation is staff-facing too: intervention content is drafted for a
        // teacher to review, not for the student it concerns.
        DB::table('ai_suggestions')
            ->whereNull('sub_institute_id')
            ->where('capability', 'generative')
            ->update(['allowed_roles' => $staffAndAdmin, 'updated_at' => now()]);

        $studentOnly = json_encode(['student']);

        foreach (self::STUDENT_PROMPTS as [$module, $label, $prompt, $sortOrder]) {
            $exists = DB::table('ai_suggestions')
                ->whereNull('sub_institute_id')
                ->where('module_key', $module)
                ->where('label', $label)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('ai_suggestions')->insert([
                'module_key' => $module,
                'capability' => 'conversational',
                'label' => $label,
                'description' => null,
                'icon' => null,
                'action_type' => 'prompt',
                'action_ref' => null,
                'prompt' => $prompt,
                'payload' => null,
                'requires_entity' => false,
                'allowed_roles' => $studentOnly,
                'required_permissions' => null,
                'sort_order' => $sortOrder,
                'status' => 1,
                'sub_institute_id' => null,
                'client_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_suggestions')) {
            return;
        }

        DB::table('ai_suggestions')
            ->whereNull('sub_institute_id')
            ->whereIn('label', array_column(self::STUDENT_PROMPTS, 1))
            ->delete();

        DB::table('ai_suggestions')
            ->whereNull('sub_institute_id')
            ->update(['allowed_roles' => null, 'updated_at' => now()]);
    }
};
